<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

/**
 * Controller Resource: Vehicle
 */
class VehicleController extends Controller
{
    public function __construct()
    {
        // Parametro rotta: {vehicle}
        $this->authorizeResource(Vehicle::class, 'vehicle');
    }

    public function index()      { return view('vehicles.index'); }
    public function create()     { return view('vehicles.create'); }
    public function show(Vehicle $vehicle)   { return view('vehicles.show', compact('vehicle')); }
    public function edit(Vehicle $vehicle)   { return view('vehicles.edit', compact('vehicle')); }

    public function store(Request $request)
    {
        // TODO: valida e crea Vehicle
        return redirect()->route('vehicles.index');
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        // TODO: valida e aggiorna Vehicle
        return redirect()->route('vehicles.show', $vehicle);
    }

    public function destroy(Vehicle $vehicle)
    {
        // TODO: elimina/archivia Vehicle
        return redirect()->route('vehicles.index');
    }
}
