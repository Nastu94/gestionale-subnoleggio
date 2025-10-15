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
        $this->authorize('contractGenerate', $rental);

        try {
            $coverage    = $request->input('coverage', null);
            $franchise   = $request->input('franchise', null);
            $expectedKm  = $request->integer('expected_km', 0);

            $service->handle($rental, $coverage, $franchise, $expectedKm);

            // Torna alla stessa pagina (wizard step 3) con toast di successo
            return back()->with('toast', [
                'type'    => 'success',
                'title'   => 'Contratto generato',
                'message' => 'Il PDF è stato salvato su questo noleggio.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            // Torna alla stessa pagina con toast di errore
            return back()->with('toast', [
                'type'    => 'error',
                'title'   => 'Errore generazione',
                'message' => $e->getMessage(), // in prod potresti mostrare un messaggio più generico
            ])->withInput();
        }
    }

}
