<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use PDF; // Assicurati di avere una libreria PDF installata, es. barryvdh/laravel-dompdf

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

    public function index()   { return view('pages.rentals.index'); }
    public function create()  { return view('pages.rentals.create'); }
    public function show(Rental $rental) { return view('pages.rentals.show', compact('rental')); }
    public function edit(Rental $rental) { return view('pages.rentals.edit', compact('rental')); }

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

    /**
     * Transizione: reserved/draft → checked_out
     * Requisiti:
     *  - Checklist pickup presente e completa
     *  - Contratto presente (e se policy lo impone: firmato)
     */
    public function checkout(Request $request, Rental $rental)
    {
        $this->authorize('checkout', $rental);

        // Verifiche di business (mvp)
        $pickup = $rental->checklists()->where('type', 'pickup')->first();
        if (!$pickup) {
            return response()->json(['ok' => false, 'message' => 'Checklist di pickup mancante.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Contratto presente su Rental
        $hasContract = $rental->getMedia('contract')->isNotEmpty();
        if (!$hasContract) {
            return response()->json(['ok' => false, 'message' => 'Contratto non presente sul rental.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Se vuoi imporre la firma: contratti firmati su Rental e su Checklist(pickup)
        $requireSigned = (bool) config('rentals.require_signed_on_checkout', false);
        if ($requireSigned) {
            $signedOnRental   = $rental->getMedia('signatures')->isNotEmpty();
            $signedOnChecklist= $pickup->getMedia('signatures')->isNotEmpty();
            if (!$signedOnRental || !$signedOnChecklist) {
                return response()->json(['ok' => false, 'message' => 'Contratto firmato assente (Rental o Checklist).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Transizione di stato
        DB::transaction(function () use ($rental) {
            $rental->status = 'checked_out';
            // Se mantieni i timestamp operativi:
            if (empty($rental->actual_pickup_at)) {
                $rental->actual_pickup_at = now();
            }
            $rental->save();
        });

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Transizione: checked_out → in_use (se usi lo step intermedio)
     */
    public function inuse(Request $request, Rental $rental)
    {
        $this->authorize('inuse', $rental);

        if ($rental->status !== 'checked_out') {
            return response()->json(['ok' => false, 'message' => 'Stato non valido per passare a in_use.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rental->status = 'in_use';
        $rental->save();

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Transizione: in_use/checked_out → checked_in
     * Requisiti:
     *  - Checklist return presente e completa
     *  - Se ci sono danni nuovi, devono avere almeno una foto
     */
    public function checkin(Request $request, Rental $rental)
    {
        $this->authorize('checkin', $rental);

        $returnChecklist = $rental->checklists()->where('type', 'return')->first();
        if (!$returnChecklist) {
            return response()->json(['ok' => false, 'message' => 'Checklist di return mancante.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Se ci sono danni segnalati nel rientro, verifica almeno una foto per danno
        $damages = $rental->damages()->whereIn('phase', ['return', 'during'])->get();
        foreach ($damages as $damage) {
            $hasPhoto = $damage->getMedia('photos')->isNotEmpty();
            if (!$hasPhoto) {
                return response()->json(['ok' => false, 'message' => "Foto mancanti per il danno ID {$damage->id}."], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        DB::transaction(function () use ($rental) {
            $rental->status = 'checked_in';
            if (empty($rental->actual_return_at)) {
                $rental->actual_return_at = now();
            }
            $rental->save();
        });

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Transizione: checked_in → closed
     * Requisiti (MVP):
     *  - Checklist return presente
     *  - Contratto firmato presente su Rental e su Checklist pickup (se policy attiva)
     *  - (Opzionale) payment_recorded = true
     */
    public function close(Request $request, Rental $rental)
    {
        $this->authorize('close', $rental);

        if ($rental->status !== 'checked_in') {
            return response()->json(['ok' => false, 'message' => 'Il noleggio deve essere in stato checked_in.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Checklist return presente?
        $returnChecklist = $rental->checklists()->where('type', 'return')->first();
        if (!$returnChecklist) {
            return response()->json(['ok' => false, 'message' => 'Checklist di return mancante.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requireSignedAtClose = (bool) config('rentals.require_signed_on_close', false);
        if ($requireSignedAtClose) {
            $pickup = $rental->checklists()->where('type', 'pickup')->first();
            $signedOnRental    = $rental->getMedia('signatures')->isNotEmpty();
            $signedOnChecklist = $pickup ? $pickup->getMedia('signatures')->isNotEmpty() : false;
            if (!$signedOnRental || !$signedOnChecklist) {
                return response()->json(['ok' => false, 'message' => 'Contratto firmato assente per la chiusura.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $requirePaymentRecorded = (bool) config('rentals.require_payment_recorded_on_close', false);
        if ($requirePaymentRecorded && !$rental->payment_recorded) {
            return response()->json(['ok' => false, 'message' => 'Pagamento non marcato come registrato.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rental->status = 'closed';
        $rental->save();

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Transizione: reserved/draft → cancelled
     */
    public function cancel(Request $request, Rental $rental)
    {
        $this->authorize('cancel', $rental);

        if (!in_array($rental->status, ['draft', 'reserved'], true)) {
            return response()->json(['ok' => false, 'message' => 'Cancellabile solo da draft/reserved.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rental->status = 'cancelled';
        $rental->save();

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Transizione: reserved → no_show
     */
    public function noshow(Request $request, Rental $rental)
    {
        $this->authorize('noshow', $rental);

        if ($rental->status !== 'reserved') {
            return response()->json(['ok' => false, 'message' => 'No-show consentito solo da reserved.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rental->status = 'no_show';
        $rental->save();

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Vista creazione checklist pickup/return
     */
    public function createChecklist(Request $request, Rental $rental)
    {
        $this->authorize('checklist.update', $rental);

        return view('pages.rentals.checklist.create', compact('rental'));
    }
}   
