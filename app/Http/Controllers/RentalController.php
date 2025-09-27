<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;

/**
 * Controller Resource: Rental (Contratti)
 */
class RentalController extends Controller
{
    public function __construct()
    {
        // Parametro rotta: {rental}
        $this->authorizeResource(Rental::class, 'rental');
    }

    public function index()   { return view('rentals.index'); }
    public function create()  { return view('rentals.create'); }
    public function show(Rental $rental) { return view('rentals.show', compact('rental')); }
    public function edit(Rental $rental) { return view('rentals.edit', compact('rental')); }

    public function store(Request $request)
    {
        // TODO: valida e crea Rental
        return redirect()->route('rentals.index');
    }

    public function update(Request $request, Rental $rental)
    {
        // TODO: valida e aggiorna Rental
        return redirect()->route('rentals.show', $rental);
    }

    public function destroy(Rental $rental)
    {
        // TODO: elimina/chiude Rental
        return redirect()->route('rentals.index');
    }
}
