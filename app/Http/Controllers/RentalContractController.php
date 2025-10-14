<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;
use App\Services\Contracts\GenerateRentalContract;

class RentalContractController extends Controller
{
    /**
     * Genera il PDF del contratto per il Rental e lo salva su Media Library (collection 'contract').
     * Policy: rentals.contract.generate
     */
    public function generate(Request $request, Rental $rental, GenerateRentalContract $service)
    {
        $this->authorize('contractGenerate', $rental); // aggiungi metodo in RentalPolicy

        // Coperture/franchigie passate dal wizard (facoltative)
        $coverage  = $request->input('coverage', null);
        $franchise = $request->input('franchise', null);
        $expectedKm = $request->integer('expected_km', 0);

        $service->handle($rental, $coverage, $franchise, $expectedKm);

        return redirect()
            ->route('rentals.show', $rental)
            ->with('status', 'Contratto generato e salvato.');
    }
}
