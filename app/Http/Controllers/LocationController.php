<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

/**
 * Controller Resource: Location (Sedi)
 */
class LocationController extends Controller
{
    public function __construct()
    {
        // Parametro rotta: {location}
        $this->authorizeResource(Location::class, 'location');
    }

    public function index()      { return view('pages.locations.index'); }
    public function create()     { return view('pages.locations.create'); }
    public function show(Location $location) { return view('pages.locations.show', compact('location')); }
    public function edit(Location $location) { return view('pages.locations.edit', compact('location')); }

    /**
     * Salvataggio NON-Livewire (fallback).
     * Per coerenza col requisito "abbinata all'utente", forziamo organization_id dell'utente.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Location::class);

        $validated = $request->validate([
            'name'         => ['required','string','min:2','max:191'],
            'address_line' => ['nullable','string','max:191'],
            'city'         => ['nullable','string','max:128'],
            'province'     => ['nullable','string','max:64'],
            'postal_code'  => ['nullable','string','max:16'],
            'country_code' => ['nullable','string','size:2'],
            'notes'        => ['nullable','string'],
        ]);

        $location = new Location();
        $location->fill($validated);
        $location->organization_id = (int) Auth::user()->organization_id;
        $location->save();

        // Con form "semplice Blade" qui potresti usare un flash o query param per un toast globale.
        // Coerentemente col tuo "toast-only", preferiamo il salvataggio via Livewire (vedi componente).
        return redirect()->route('locations.show', $location);
    }

    public function update(Request $request, Location $location)
    {
        // TODO: valida e aggiorna Location
        return redirect()->route('locations.show', $location);
    }

    public function destroy(Location $location)
    {
        // TODO: elimina/archivia Location
        return redirect()->route('locations.index');
    }
}
