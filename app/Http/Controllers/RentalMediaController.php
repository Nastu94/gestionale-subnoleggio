<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use App\Models\{Rental, RentalChecklist, RentalDamage, Organization};
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Controller dedicato alla gestione Media per il flusso noleggio.
 * - Non appesantisce RentalController.
 * - Usa le collection definite sui Model.
 */
class RentalMediaController extends Controller
{
    /**
     * Upload contratto (PDF generato dal gestionale).
     * Collection: Rental -> contract
     */
    public function storeContract(Request $request, Rental $rental)
    {
        $this->authorize('contractGenerate', $rental);
        $this->authorize('uploadMedia', $rental); // media.upload "generico"

        $request->validate([
            'file' => ['required','file','mimes:pdf','max:20480'], // 20MB
        ]);

        // Versioniamo: ogni upload è una nuova entry
        $media = $rental->addMediaFromRequest('file')
            ->usingName('rental-contract')
            ->toMediaCollection('contract');

        return response()->json([
            'ok' => true,
            'media_id' => $media->id,
        ], Response::HTTP_CREATED);
    }

    /**
     * Upload contratto FIRMATO (PDF/immagine).
     * Duplica su:
     *  - Rental -> signatures
     *  - Checklist(PICKUP) -> signatures
     */
    public function storeSignedContract(Request $request, Rental $rental)
    {
        $this->authorize('contractUploadSigned', $rental);
        $this->authorize('uploadMedia', $rental);

        $request->validate([
            'file' => ['required','file','mimetypes:application/pdf,image/jpeg,image/png','max:20480'],
        ]);

        $pickup = $rental->checklists()->where('type','pickup')->first();

        try {
            // 1) Salvo UNA volta sul Rental
            $mediaRental = $rental->addMediaFromRequest('file')
                ->usingName('rental-contract-signed')
                ->toMediaCollection('signatures'); // se è singleFile(), sostituisce in sicurezza

            // 2) Duplico sulla checklist senza riusare l'UploadedFile
            if ($pickup) {
                $mediaRental->copy($pickup, 'signatures');
            }

            return response()->json(['ok' => true], \Symfony\Component\HttpFoundation\Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            // rollback “manuale” del file/record appena creato, così non restano cartelle vuote
            if (isset($mediaRental)) {
                try { $mediaRental->delete(); } catch (\Throwable $ignore) {}
            }
            throw $e;
        }
    }

    /**
     * Apertura/visualizzazione inline di un Media (PDF/immagine).
     * Regole:
     *  - Verifica permessi in base al "padre" del media.
     *  - Ritorna il file con MIME corretto per visualizzazione inline.
     */
    public function open(Media $media)
    {
        // Autorizzazione sul "padre"
        $parent = $media->model;
        if ($parent instanceof Rental || $parent instanceof RentalChecklist) {
            $this->authorize('view', $parent);
        } else {
            abort(403, 'Accesso negato.');
        }

        // 1) Tentativo robusto: path assoluto fornito da Spatie
        $fullPath = $media->getPath(); // assoluto, es: C:\laragon\...\storage\app\public\...
        if (is_string($fullPath) && is_file($fullPath)) {
            $mime = $media->mime_type ?: (function_exists('mime_content_type') ? mime_content_type($fullPath) : 'application/octet-stream');

            return response()->file($fullPath, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
                'Cache-Control'       => 'private, max-age=0, no-store',
            ]);
        }

        // 2) Se è su disco 'public', prova URL pubblica (/storage symlink)
        if ($media->disk === 'public') {
            return redirect()->away($media->getUrl());
        }

        // 3) Altrimenti 404 coerente
        abort(404, 'File non trovato.');
    }

    /**
     * Upload foto su Checklist (pickup/return).
     * Collection: RentalChecklist -> photos
     * Regole:
     *  - Vietato se la checklist è locked (423 Locked).
     *  - Ritorna anche uuid/url/name/size per popolare la mini-tabella in UI.
     */
    public function storeChecklistPhoto(Request $request, RentalChecklist $checklist)
    {
        $this->authorize('uploadPhoto', $checklist);

        // ✅ Validazione file e "kind" (categoria foto)
        $validated = $request->validate([
            'file' => ['required','file','mimetypes:image/jpeg,image/png','max:20480'],
            'kind' => ['required','in:odometer,fuel,exterior'], // 👈 distinguo i gruppi
        ]);

        // Salvo e imposto la custom_property "kind"
        $media = $checklist->addMediaFromRequest('file')
            ->usingName('checklist-'.$validated['kind'])
            ->withCustomProperties(['kind' => $validated['kind']])
            ->toMediaCollection('photos');

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => $media->getUrl(),
            'name'     => $media->file_name,
            'size'     => $media->size,
            'kind'     => $validated['kind'], // 👈 comodo per la UI
            'msg'      => 'Foto caricata.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Lista foto del Danno (JSON).
     * Collection: RentalDamage -> photos
     * Autorizzazione: riuso la stessa policy dell'upload per coerenza.
     */
    public function indexDamagePhotos(Request $request, RentalDamage $damage)
    {
        $this->authorize('uploadPhoto', $damage);

        // Se checklist padre è locked: consultazione ok, ma non si potrà eliminare lato UI.
        $items = $damage->getMedia('photos')->map(function ($m) {
            return [
                'id'         => (int) $m->id,
                'uuid'       => $m->uuid,
                'url'        => $m->getUrl(),
                'name'       => $m->file_name,
                'size'       => (int) $m->size,
                'delete_url' => route('media.destroy', $m), // usata dalla tabella per "Elimina"
            ];
        })->values()->all();

        return response()->json([
            'ok'    => true,
            'items' => $items,
        ]);
    }

    /**
     * Upload foto su Danno.
     * Collection: RentalDamage -> photos
     * Regole:
     *  - Vietato se la checklist padre è locked (423 Locked).
     *  - Ritorna anche uuid/url/name/size per la mini-tabella.
     */
    public function storeDamagePhoto(Request $request, RentalDamage $damage)
    {
        $this->authorize('uploadPhoto', $damage);

        // Se il danno appartiene a una checklist bloccata → vietato
        $parentChecklist = $damage->checklist ?? null;
        if ($parentChecklist && $parentChecklist->isLocked()) {
            return response()->json([
                'ok' => false,
                'message' => 'Checklist bloccata: non è possibile caricare media del danno.',
            ], Response::HTTP_LOCKED);
        }

        $request->validate([
            'file' => ['required','file','mimetypes:image/jpeg,image/png','max:20480'],
        ]);

        $media = $damage->addMediaFromRequest('file')
            ->usingName('damage-photo')
            ->toMediaCollection('photos');

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => $media->getUrl(),
            'name'     => $media->file_name,
            'size'     => $media->size,
        ], Response::HTTP_CREATED);
    }

    /**
     * Upload checklist FIRMATA (PDF/immagine).
     * Collection (in base al type):
     *  - pickup  => checklist_pickup_signed
     *  - return  => checklist_return_signed
     *
     * Effetti:
     *  - Allega il file firmato.
     *  - Applica il LOCK persistente (locked_at/by/reason, signed_media_id).
     *  - Se già locked, ritorna 409 Conflict (già bloccata).
     */
    public function storeChecklistSigned(Request $request, RentalChecklist $checklist)
    {
        $this->authorize('uploadSignature', $checklist);

        if ($checklist->isLocked()) {
            return response()->json([
                'ok' => false,
                'message' => 'Checklist già bloccata da un file firmato.',
            ], Response::HTTP_CONFLICT);
        }

        $request->validate([
            'file' => ['required','file','mimetypes:application/pdf,image/jpeg,image/png','max:20480'],
        ]);

        $media = null;

        DB::transaction(function () use ($request, $checklist, &$media) {
            // Scegli la collection firmata corretta
            $collection = $checklist->signedCollectionName();

            // Allega il firmato (singleFile: sovrascrive eventuali caricamenti precedenti nella stessa collection)
            $media = $checklist->addMediaFromRequest('file')
                ->usingName('checklist-signed')
                ->toMediaCollection($collection);

            // Applica LOCK persistente (Opzione B)
            $checklist->signed_by_customer = true;
            $checklist->signed_by_operator = true;
            $checklist->signature_media_uuid = $media->uuid;
            $checklist->locked_at         = now();
            $checklist->locked_by_user_id = $request->user()->id ?? null;
            $checklist->locked_reason     = 'customer_signed_pdf';
            $checklist->signed_media_id   = $media->id;
            $checklist->save();
        });

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => $media->getUrl(),
            'name'     => $media->file_name,
            'size'     => $media->size,
            'locked'   => true,
        ], Response::HTTP_CREATED);
    }

    /**
     * Upload documenti vari su Rental (privacy, ID, email, ecc.).
     * Collection: Rental -> documents
     */
    public function storeRentalDocument(Request $request, Rental $rental)
    {
        // Autorizzazioni coerenti con il tuo flusso
        $this->authorize('uploadMedia', $rental);
        Gate::authorize('attachRentalDocument', $rental); // se lo usi davvero

        // ✅ Validazioni: collection opzionale ma vincolata; file fino a 20MB
        $validated = $request->validate([
            'collection' => ['nullable','in:documents,id_card,driver_license,privacy,other'],
            'file'       => ['required','file','mimes:pdf,jpg,jpeg,png','max:20480'],
        ]);

        // Default di sicurezza: se non arriva la collection, usa "documents"
        $collection = $validated['collection'] ?? 'documents';

        // Salvataggio su Media Library direttamente dal payload della Request
        $media = $rental->addMediaFromRequest('file')
            ->usingFileName($request->file('file')->getClientOriginalName() ?: 'allegato')
            ->toMediaCollection($collection);

        // Risposta JSON per la UI (AJAX)
        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'url'      => $media->getUrl(),
            'name'     => $media->file_name,
            'col'      => $media->collection_name,
            'size'     => $media->size,
            'msg'      => 'Documento caricato.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Cancellazione di un Media.
     * Regole:
     *  - Se il padre è una RentalChecklist locked → 423 Locked.
     *  - Se il padre è un RentalDamage e la sua checklist è locked → 423 Locked.
     *  - Se il padre è un Rental e il media è nella collection "signatures" (contratto firmato) → 423 Locked.
     *  - Altrimenti: verifica permessi e consenti la delete.
     */
    public function destroy(Media $media)
    {
        $model = $media->model; // relazione morph-to al "padre" del media

        if ($model instanceof \App\Models\RentalChecklist) {
            $this->authorize('deleteMedia', $model);

            // ❌ Checklist bloccata: nessuna cancellazione consentita
            if ($model->isLocked()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Checklist bloccata: non è possibile eliminare media.',
                ], Response::HTTP_LOCKED);
            }

            // ❌ Protezione esplicita del media firmato che ha causato il lock
            if ($model->signed_media_id && (int) $model->signed_media_id === (int) $media->id) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Il media firmato non può essere eliminato.',
                ], Response::HTTP_LOCKED);
            }
        }
        elseif ($model instanceof \App\Models\RentalDamage) {
            $this->authorize('deleteMedia', $model);

            // ❌ Se la checklist padre è locked, non si eliminano media dei danni
            $parentChecklist = $model->checklist ?? null;
            if ($parentChecklist && $parentChecklist->isLocked()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Checklist bloccata: non è possibile eliminare media del danno.',
                ], Response::HTTP_LOCKED);
            }
        }
        elseif ($model instanceof \App\Models\Rental) {
            $this->authorize('deleteMedia', $model);

            // ✅ Non agganciamo il lock della checklist ai documenti generali del Rental,
            //    ma proteggiamo i CONTRATTI FIRMATI:
            if ($media->collection_name === 'signatures') {
                // Firma del contratto: prova documentale → non eliminabile
                return response()->json([
                    'ok'      => false,
                    'message' => 'Contratto firmato: il file non può essere eliminato.',
                ], Response::HTTP_LOCKED);
            }

            // Per altre collection (es. documents, contract bozza, ecc.) si procede
        }
        else {
            return response()->json([
                'ok'      => false,
                'message' => 'Operazione non consentita per questo modello.',
            ], Response::HTTP_FORBIDDEN);
        }

        $media->delete();

        return response()->json(['ok' => true]);
    }
    
    /* ==========================================================
     |  FIRME GRAFICHE
     |  Rental collections:
     |   - signature_customer (singleFile)
     |   - signature_lessor   (singleFile)  -> override sul noleggio
     ========================================================== */

    /**
     * Upload firma grafica CLIENTE (immagine).
     * Collection: Rental -> signature_customer (singleFile)
     */
    public function storeCustomerSignature(Request $request, Rental $rental)
    {
        $this->authorize('uploadMedia', $rental);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:4096'],
        ]);

        $media = $rental->addMediaFromRequest('file')
            ->usingName('signature-customer')
            ->toMediaCollection('signature_customer');

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => route('media.open', $media),
            'name'     => $media->file_name,
            'size'     => (int) $media->size,
        ], Response::HTTP_CREATED);
    }

    /**
     * Cancella firma grafica CLIENTE.
     */
    public function destroyCustomerSignature(Request $request, Rental $rental)
    {
        $this->authorize('uploadMedia', $rental);

        $rental->clearMediaCollection('signature_customer');

        return response()->json(['ok' => true]);
    }

    /**
     * Upload firma grafica NOLEGGIANTE (override sul Rental).
     * Collection: Rental -> signature_lessor (singleFile)
     */
    public function storeLessorSignatureOverride(Request $request, Rental $rental)
    {
        $this->authorize('uploadMedia', $rental);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:4096'],
        ]);

        $media = $rental->addMediaFromRequest('file')
            ->usingName('signature-lessor-override')
            ->toMediaCollection('signature_lessor');

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => route('media.open', $media),
            'name'     => $media->file_name,
            'size'     => (int) $media->size,
        ], Response::HTTP_CREATED);
    }

    /**
     * Cancella firma grafica NOLEGGIANTE (override Rental).
     */
    public function destroyLessorSignatureOverride(Request $request, Rental $rental)
    {
        $this->authorize('uploadMedia', $rental);

        $rental->clearMediaCollection('signature_lessor');

        return response()->json(['ok' => true]);
    }
    
    /**
     * Upload FIRMA NOLEGGIANTE (immagine PNG/JPG) salvata come override sul Rental.
     * Collection: Rental -> signature_lessor (singleFile consigliata)
     */
    public function storeLessorSignature(Request $request, Rental $rental)
    {
        $this->authorize('contractUploadSigned', $rental);
        $this->authorize('uploadMedia', $rental);

        $request->validate([
            'file' => ['required','file','mimetypes:image/png,image/jpeg','max:5120'], // 5MB (firma)
        ]);

        $media = $rental->addMediaFromRequest('file')
            ->usingName('rental-signature-lessor')
            ->toMediaCollection('signature_lessor');

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => route('media.open', $media), // usa il tuo open() per permessi + inline
            'name'     => $media->file_name,
            'size'     => $media->size,
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete FIRMA NOLEGGIANTE (override sul Rental).
     */
    public function destroyLessorSignature(Request $request, Rental $rental)
    {
        $this->authorize('contractUploadSigned', $rental);
        $this->authorize('deleteMedia', $rental);

        $m = $rental->getFirstMedia('signature_lessor');
        if ($m) {
            $m->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Upload firma aziendale di default.
     * Collection: Organization -> signature_company
     */
    public function storeOrganizationSignature(Request $request, Organization $organization)
    {
        // Scegli tu la policy corretta:
        // - se hai policy Organization: update
        $this->authorize('update', $organization);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:4096'],
        ]);

        $media = $organization->addMediaFromRequest('file')
            ->usingName('signature-company')
            ->toMediaCollection('signature_company'); // singleFile consigliato sul model

        return response()->json([
            'ok'       => true,
            'media_id' => $media->id,
            'uuid'     => $media->uuid,
            'url'      => route('media.open', $media),
            'name'     => $media->file_name,
            'size'     => (int) $media->size,
        ], Response::HTTP_CREATED);
    }

    /**
     * Cancella firma aziendale di default.
     */
    public function destroyOrganizationSignature(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $organization->clearMediaCollection('signature_company');

        return response()->json(['ok' => true]);
    }

}
