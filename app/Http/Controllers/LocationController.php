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

    public function store(Request $request)
    {
        // TODO: valida e crea Location
        return redirect()->route('locations.index');
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
