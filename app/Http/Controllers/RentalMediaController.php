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
     */
    public function storeChecklistPhoto(Request $request, RentalChecklist $checklist)
    {
        $this->authorize('uploadPhoto', $checklist);

        $request->validate([
            'file' => ['required','file','mimetypes:image/jpeg,image/png','max:20480'],
        ]);

        $media = $checklist->addMediaFromRequest('file')
            ->usingName('checklist-photo')
            ->toMediaCollection('photos');

        return response()->json(['ok' => true, 'media_id' => $media->id], Response::HTTP_CREATED);
    }

    /**
     * Upload foto su Danno.
     * Collection: RentalDamage -> photos
     */
    public function storeDamagePhoto(Request $request, RentalDamage $damage)
    {
        $this->authorize('uploadPhoto', $damage);

        $request->validate([
            'file' => ['required','file','mimetypes:image/jpeg,image/png','max:20480'],
        ]);

        $media = $damage->addMediaFromRequest('file')
            ->usingName('damage-photo')
            ->toMediaCollection('photos');

        return response()->json(['ok' => true, 'media_id' => $media->id], Response::HTTP_CREATED);
    }

    /**
     * Upload documenti vari su Rental (privacy, ID, email, ecc.).
     * Collection: Rental -> documents
     */
    public function storeRentalDocument(Request $request, Rental $rental)
    {
        $this->authorize('uploadMedia', $rental);
        Gate::authorize('attachRentalDocument', $rental); // opzionale: se definisci un gate/permesso specifico

        $request->validate([
            'file' => ['required','file','max:20480'], // accettiamo qualsiasi tipo utile
        ]);

        $media = $rental->addMediaFromRequest('file')
            ->usingName('rental-document')
            ->toMediaCollection('documents');

        return response()->json(['ok' => true, 'media_id' => $media->id], Response::HTTP_CREATED);
    }

    /**
     * Cancellazione di un Media.
     * Richiede permesso media.delete e ownership coerente al padre.
     */
    public function destroy(Media $media)
    {
        // Verifica ownership: recupera il "model padre" e chiama la relativa policy deleteMedia
        $model = $media->model; // morph-to

        // Dispatch su tipo modello
        if ($model instanceof Rental) {
            $this->authorize('deleteMedia', $model);
        } elseif ($model instanceof RentalChecklist) {
            $this->authorize('deleteMedia', $model);
        } elseif ($model instanceof RentalDamage) {
            $this->authorize('deleteMedia', $model);
        } else {
            abort(Response::HTTP_FORBIDDEN, 'Operazione non consentita per questo modello.');
        }

        $media->delete();

        return response()->json(['ok' => true]);
    }
}
