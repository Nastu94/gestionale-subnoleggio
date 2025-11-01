<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

use App\Models\{Rental, RentalChecklist, RentalDamage};
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

        DB::transaction(function () use ($request, $rental) {
            // 1) Allego su Rental -> signatures
            $rental->addMediaFromRequest('file')
                ->usingName('rental-contract-signed')
                ->toMediaCollection('signatures');

            // 2) Allego anche sulla checklist di pickup, se presente
            $pickup = $rental->checklists()->where('type','pickup')->first();
            if ($pickup) {
                $pickup->addMediaFromRequest('file')
                    ->usingName('rental-contract-signed')
                    ->toMediaCollection('signatures');
            }
        });

        return response()->json(['ok' => true], Response::HTTP_CREATED);
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
                ], \Symfony\Component\HttpFoundation\Response::HTTP_LOCKED);
            }

            // ❌ Protezione esplicita del media firmato che ha causato il lock
            if ($model->signed_media_id && (int) $model->signed_media_id === (int) $media->id) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Il media firmato non può essere eliminato.',
                ], \Symfony\Component\HttpFoundation\Response::HTTP_LOCKED);
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
                ], \Symfony\Component\HttpFoundation\Response::HTTP_LOCKED);
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
                ], \Symfony\Component\HttpFoundation\Response::HTTP_LOCKED);
            }

            // Per altre collection (es. documents, contract bozza, ecc.) si procede
        }
        else {
            return response()->json([
                'ok'      => false,
                'message' => 'Operazione non consentita per questo modello.',
            ], \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
        }

        $media->delete();

        return response()->json(['ok' => true]);
    }
}
