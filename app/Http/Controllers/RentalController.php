<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\RentalCharge;
use App\Domain\Pricing\VehiclePricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $signedOnRental   = $rental->getMedia('signatures')->isNotEmpty();
        $signedOnChecklist= $pickup->getMedia('checklist_pickup_signed')->isNotEmpty();
        if (!$signedOnChecklist) {
            return response()->json(['ok' => false, 'message' => 'Checklist pickup firmata assente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } else if (!$signedOnRental) {
            return response()->json(['ok' => false, 'message' => 'Contratto firmato assente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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

        if ($rental->status !== 'draft') {
            return response()->json(['ok' => false, 'message' => 'No-show consentito solo da draft.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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

    /**
     * Registra pagamento sul noleggio
     */
    public function storePayment(Request $request, Rental $rental)
    {
        $this->authorize('update', $rental);

        $validKinds = [
            RentalCharge::KIND_BASE,
            RentalCharge::KIND_DISTANCE_OVERAGE,
            RentalCharge::KIND_DAMAGE,
            RentalCharge::KIND_SURCHARGE,
            RentalCharge::KIND_FINE,
            RentalCharge::KIND_OTHER,
        ];

        $data = $request->validate([
            'kind'              => [
                'required', 
                Rule::in($validKinds), 
                Rule::unique('rental_charges', 'kind')
                    ->where(fn ($q) => $q->where('rental_id', $rental->id)
                                        ->whereNull('deleted_at')),
            ],
            'amount'            => ['required','numeric','min:0.01'],
            'payment_method'    => ['required','string','max:255'],
            'description'       => ['nullable','string','max:255'],
            'is_commissionable' => ['sometimes','boolean'], // override opzionale
        ],
        [
            'kind.unique' => 'Esiste già un addebito di questo tipo per il noleggio.',
            'amount.min'  => 'L\'importo deve essere almeno :min.',
            'amount.required' => 'L\'importo è obbligatorio.',
            'payment_method.required' => 'Il metodo di pagamento è obbligatorio.',
            'payment_method.string' => 'Il metodo di pagamento deve essere una stringa.',
            'payment_method.max' => 'Il metodo di pagamento non può superare i :max caratteri.',
        ]);

        DB::transaction(function () use ($rental, $data) {
            // Se non specificato, commissionabile solo per base/overage
            $isCommissionable = array_key_exists('is_commissionable', $data)
                ? (bool)$data['is_commissionable']
                : in_array($data['kind'], [
                    RentalCharge::KIND_BASE,
                    RentalCharge::KIND_DISTANCE_OVERAGE,
                ], true);

            RentalCharge::create([
                'rental_id'           => $rental->id,
                'kind'                => $data['kind'],
                'description'         => $data['description'] ?? null,
                'amount'              => $data['amount'],
                'is_commissionable'   => $isCommissionable,
                'payment_method'      => $data['payment_method'],
                'payment_recorded'    => true,
                'payment_recorded_at' => now(),
                'created_by'          => auth()->id(),
            ]);

            // Se sto registrando da draft/reserved, resto/torno in reserved
            if (in_array($rental->status, ['draft','reserved'], true)) {
                $rental->forceFill(['status' => 'reserved'])->save();
            }
        });

        // aggiorna flag per la UI
        $rental->refresh();

        return response()->json([
            'ok'      => true,
            'message' => 'Pagamento registrato con successo.',
            'flags'   => [
                'has_base_payment'             => $rental->has_base_payment,
                'needs_distance_overage'       => $rental->needs_distance_overage_payment,
                'has_distance_overage_payment' => $rental->has_distance_overage_payment,
            ],
            'status'  => $rental->status,
        ], Response::HTTP_OK);
    }

    /**
     * Calcola l'addebito per km eccedenti
     */
    public function distanceOverage(Rental $rental, VehiclePricingService $svc)
    {
        // servono km_in/km_out; se mancano, niente badge
        if (is_null($rental->mileage_out) || is_null($rental->mileage_in)) {
            return response()->json([
                'ok' => true,
                'has_data' => false,
                'cents' => 0,
                'amount' => '0.00',
            ]);
        }

        // listino attivo per il renter corrente del veicolo
        $pl = $svc->findActivePricelistForCurrentRenter($rental->vehicle);
        if (!$pl) {
            return response()->json([
                'ok' => false,
                'message' => 'Nessun listino attivo per il renter corrente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // giorni reali se disponibili, altrimenti pianificati
        $pickupAt  = $rental->actual_pickup_at  ?? $rental->planned_pickup_at  ?? now();
        $returnAt  = $rental->actual_return_at  ?? $rental->planned_return_at  ?? now();

        // km percorsi (min 0)
        $km = max(0, (int)$rental->mileage_in - (int)$rental->mileage_out);

        $quote  = $svc->quote($pl, $pickupAt, $returnAt, $km);
        $cents  = (int)($quote['km_extra'] ?? 0);
        $amount = number_format($cents / 100, 2, '.', '');

        return response()->json([
            'ok'      => true,
            'has_data'=> true,
            'cents'   => $cents,
            'amount'  => $amount,
        ]);
    }
}
