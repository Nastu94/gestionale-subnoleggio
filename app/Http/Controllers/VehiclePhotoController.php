<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
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
        // Se usi le policy, puoi tenere anche questo:
        // $this->authorize('update', $vehicle);

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
