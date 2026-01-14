<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleDamage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VehiclePhotoController extends Controller
{
    /**
     * Carica una foto nella collection 'vehicle_photos' del veicolo.
     * Protetto da permessi (routes) + opzionale policy update.
     */
    public function store(Request $request, Vehicle $vehicle)
    {
        // Autorizzazione upload foto veicolo (admin o renter assegnato)
        $this->authorize('uploadPhoto', $vehicle);

        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Salva su disco 'public' di default (MediaLibrary)
        $vehicle->addMediaFromRequest('photo')
            ->usingFileName($this->uniqueFileName($request->file('photo')))
            ->toMediaCollection('vehicle_photos');

        return back()->with('status', 'Foto caricata');
    }

    /**
     * Upload foto su Danno manuale/inspection/service.
     * Salva su Vehicle -> collection: vehicle_damage_photos
     * con custom_property damage_id = $damage->id
     */
    public function storeForManualDamage(Request $request, Vehicle $vehicle, VehicleDamage $damage)
    {

        // Coerenza: il danno deve appartenere al veicolo e NON essere da rental
        if ((int)$damage->vehicle_id !== (int)$vehicle->id || $damage->source === 'rental') {
            return response()->json(['ok'=>false,'message'=>'Danno non valido per upload manuale.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->validate([
            'file' => ['required','file','mimetypes:image/jpeg,image/png,image/webp','max:20480'],
        ]);

        $media = $vehicle
            ->addMediaFromRequest('file')
            ->withCustomProperties(['damage_id' => $damage->id])
            ->usingName("vehicle-damage-{$damage->id}")
            ->toMediaCollection('vehicle_damage_photos');

        return response()->json([
            'ok'         => true,
            'media_id'   => $media->id,
            'uuid'       => $media->uuid,
            'url'        => $media->getUrl(),                              // originale/preview
            'preview_url'=> $media->hasGeneratedConversion('vd_preview') ? $media->getUrl('vd_preview') : $media->getUrl(),
            'thumb_url'  => $media->hasGeneratedConversion('vd_thumb')   ? $media->getUrl('vd_thumb')   : $media->getUrl(),
            'name'       => $media->file_name,
            'size'       => $media->size,
            'origin'     => 'vehicle_damage',
        ], Response::HTTP_CREATED);
    }

    /**
     * Elimina una foto del veicolo.
     */
    public function destroy(Request $request, Vehicle $vehicle, Media $media)
    {
        // $this->authorize('update', $vehicle);

        // Sicurezza: assicurati che la media appartenga al veicolo
        if ($media->model_type !== Vehicle::class || (int) $media->model_id !== (int) $vehicle->id) {
            abort(404);
        }

        $media->delete();

        return back()->with('status', 'Foto eliminata');
    }

    private function uniqueFileName(UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        return uniqid('veh_', true).'.'.$ext;
    }
}
