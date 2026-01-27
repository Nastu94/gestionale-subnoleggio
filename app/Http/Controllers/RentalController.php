<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\RentalCharge;
use App\Models\RentalChecklist;
use App\Domain\Pricing\VehiclePricingService;
use App\Domain\Rentals\Guards\CloseRentalGuard;
use App\Domain\Fees\AdminFeeResolver;
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
            // ✅ Eliminato "checked_out": se il veicolo esce, è già "in_use"
            $rental->status = 'in_use';

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
        $signedOnRental = $rental->getMedia('signatures')->isNotEmpty();

        /**
         * ✅ Maggiore copertura: la firma pickup può essere salvata in collection diverse in base al flusso.
         * - "checklist_pickup_signed" (specifica pickup)
         * - "signatures" (generico)
         */
        $signedOnChecklist = $pickup->getMedia('checklist_pickup_signed')->isNotEmpty()
            || $pickup->getMedia('signatures')->isNotEmpty();

        if (!$signedOnChecklist) {
            return response()->json(['ok' => false, 'message' => 'Checklist pickup firmata assente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } else if (!$signedOnRental) {
            return response()->json(['ok' => false, 'message' => 'Contratto firmato assente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!in_array($rental->status, ['draft', 'checked_out', 'reserved'], true)) {
            return response()->json(['ok' => false, 'message' => 'Stato non valido per passare a in_use.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($rental) {
            $rental->status = 'in_use';

            if (empty($rental->actual_pickup_at)) {
                $rental->actual_pickup_at = now();
            }

            $rental->save();
        });

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

        if (!in_array($rental->status, ['in_use', 'checked_out'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Stato non valido per effettuare il check-in.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
    public function close(Request $request, Rental $rental, AdminFeeResolver $fees, CloseRentalGuard $guard)
    {
        // 1) Permesso
        $this->authorize('close', $rental);

        // 2) Regole (qui niente config: imposta tu i default/override)
        $rules = [
            'require_signed'       => false, // cambia a true se vuoi
            'require_base_payment' => true,  // nuova logica "charges"
            'grace_minutes'        => 0,     // ricalcolo snapshot disattivato
        ];

        // 3) Verifica regole
        $res = $guard->check($rental, $rules);

        // Consenti override SOLO se il codice è "snapshot_locked"
        if (!$res['ok']) {
            $canOverride = ($res['code'] === 'snapshot_locked') && auth()->user()->can('rentals.close.override');
            if (!$canOverride) {
                return response()->json([
                    'ok' => false, 'code' => $res['code'], 'message' => $res['message'],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // 4) Chiusura + snapshot fee admin (solo se org = renter)
        DB::transaction(function () use ($rental, $fees) {
            $isFirstClose = is_null($rental->closed_at);
            $closedAt     = $isFirstClose ? now() : $rental->closed_at;

            $rental->loadMissing('organization');
            $isRenter = optional($rental->organization)->type === 'renter';

            if ($isRenter) {
                $calc = $fees->calculateForRental($rental, $rental->actual_return_at ?: $closedAt);

                $rental->forceFill([
                    'admin_fee_percent' => $calc['percent'], // es. float|null
                    'admin_fee_amount'  => $calc['amount'],  // es. decimal(10,2)
                    'status'            => 'closed',
                    'closed_at'         => $closedAt,
                    'closed_by'         => $isFirstClose ? optional(auth()->user())->id : $rental->closed_by,
                ])->save();
            } else {
                $rental->forceFill([
                    'status'    => 'closed',
                    'closed_at' => $closedAt,
                    'closed_by' => $isFirstClose ? optional(auth()->user())->id : $rental->closed_by,
                ])->save();
            }
        });

        $rental->refresh();

        return response()->json([
            'ok'     => true,
            'status' => $rental->status,
            'flags'  => [
                'has_base_payment'             => $rental->has_base_payment,
                'needs_distance_overage'       => $rental->needs_distance_overage_payment,
                'has_distance_overage_payment' => $rental->has_distance_overage_payment,
            ],
            'admin_fee' => [
                'percent' => $rental->admin_fee_percent,
                'amount'  => $rental->admin_fee_amount,
            ],
            'closed_at' => optional($rental->closed_at)->toIso8601String(),
        ], Response::HTTP_OK);
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
     * Transizione: reserved/draft → cancelled (ex no_show)
     * Nota: lo stato "no_show" viene eliminato e ricondotto a "cancelled".
     */
    public function noshow(Request $request, Rental $rental)
    {
        $this->authorize('noshow', $rental);

        // ✅ Consenti solo dai casi “pre-uso”
        if (!in_array($rental->status, ['draft', 'reserved'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'No-show (ora annullamento) consentito solo da draft/reserved.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rental->status = 'cancelled';
        $rental->save();

        return response()->json(['ok' => true, 'status' => $rental->status], Response::HTTP_OK);
    }

    /**
     * Vista creazione checklist pickup/return
     */
    public function createChecklist(Request $request, Rental $rental)
    {
        // 1) Il renter deve poter vedere quel Rental
        $this->authorize('view', $rental);

        // 2) Deve avere il permesso di creare una checklist
        $this->authorize('create', RentalChecklist::class);

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
            RentalCharge::KIND_ACCONTO,
            RentalCharge::KIND_BASE_PLUS_DISTANCE_OVERAGE,
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
            'payment_notes'     => ['nullable','string','max:255'], // note dal modale (UI)
            'payment_reference' => ['nullable','string','max:255'], // riferimento dal modale (UI)
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
                    RentalCharge::KIND_BASE_PLUS_DISTANCE_OVERAGE,
                    RentalCharge::KIND_ACCONTO,
                ], true) && $rental->assignment_id !== null;

            RentalCharge::create([
                'rental_id'           => $rental->id,
                'kind'                => $data['kind'],
                'description'         => $data['description'] ?? $data['payment_notes'] ?? null,
                'amount'              => $data['amount'],
                'is_commissionable'   => $isCommissionable,
                'payment_method'      => $data['payment_method'],
                'payment_recorded'    => true,
                'payment_recorded_at' => now(),
                'created_by'          => auth()->id(),
            ]);
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

            /**
             * ✅ Totale acconti PAGATI sul noleggio (serve alla UI per calcolare il residuo quota base).
             * Lo ricalcoliamo sempre a DB dopo il refresh così è fonte unica e aggiornata.
             */
            'acconto_paid_total' => (float) $rental->charges()
                ->where('kind', RentalCharge::KIND_ACCONTO)
                ->where('payment_recorded', true)
                ->sum('amount'),

            'status'  => $rental->status,
        ], Response::HTTP_OK);
    }

    /**
     * Calcola l'addebito per km eccedenti
     * - Ritorna anche "has_payment" per allineare UI/JS e prevenire codice morto.
     */
    public function distanceOverage(Rental $rental)
    {
        /**
         * ✅ Fonte unica: flag "overage pagato?"
         * (manteniamo lo stesso comportamento UI)
         */
        $hasPayment = (bool) $rental->has_distance_overage_payment;

        /**
         * ✅ Fonte unica: km extra calcolati dal Model
         * Il Model:
         * - usa mileage checklist se presenti, fallback su mileage_in/out
         * - usa snapshot per inclusi (km_daily_limit * days)
         */
        $kmExtra = (int) $rental->distance_overage_km;

        // Se non ci sono km extra, rispondo in modo "vuoto" ma coerente
        if ($kmExtra <= 0) {
            return response()->json([
                'ok'        => true,
                'has_data'  => true,
                'km_extra'  => 0,
                'cents'     => 0,
                'amount'    => 0,
                'has_payment' => $hasPayment,
            ]);
        }

        /**
         * ✅ Prezzo al km (cents) dallo snapshot congelato.
         * Nel tuo esempio: extra_km_cents = 59
         */
        $snap = (array) ($rental->contractSnapshot?->pricing_snapshot ?? []);
        $extraKmCents = (int) ($snap['extra_km_cents'] ?? 0);

        // Se manca la tariffa extra, meglio non inventare importi
        if ($extraKmCents <= 0) {
            return response()->json([
                'ok'        => true,
                'has_data'  => true,
                'km_extra'  => $kmExtra,
                'cents'     => 0,
                'amount'    => 0,
                'has_payment' => $hasPayment,
            ]);
        }

        $cents  = $kmExtra * $extraKmCents;
        $amount = round($cents / 100, 2);

        return response()->json([
            'ok'        => true,
            'has_data'  => true,
            'km_extra'  => $kmExtra,
            'cents'     => $cents,
            'amount'    => $amount,
            'has_payment' => $hasPayment,
        ]);
    }
}
