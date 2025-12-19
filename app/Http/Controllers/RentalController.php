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

        if (!in_array($rental->status, ['draft', 'checked_out'], true)) {
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

        // La UI invia "payment_notes" e "payment_reference": li riconduciamo qui.
        $description = $data['description'] ?? $data['payment_notes'] ?? null;

        // Se c'è un riferimento, lo includiamo in modo compatto (senza superare 255 char).
        if (!empty($data['payment_reference'])) {
            $prefix = 'Rif: ' . trim((string) $data['payment_reference']);
            $description = $description ? ($prefix . ' | ' . $description) : $prefix;

            // Safety: tronca a 255 per rispettare la validazione/colonna.
            $description = mb_substr($description, 0, 255);
        }

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
                'description'         => $description,
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
            'status'  => $rental->status,
        ], Response::HTTP_OK);
    }

    /**
     * Calcola l'addebito per km eccedenti
     * - Ritorna anche "has_payment" per allineare UI/JS e prevenire codice morto.
     */
    public function distanceOverage(Rental $rental, VehiclePricingService $svc)
    {
        /**
         * ✅ Fonte unica backend: "overage pagato?"
         * Usato dalla UI per:
         * - badge km extra
         * - abilitazione chiusura
         * - pre-compilazioni modale
         */
        $hasPayment = (bool) $rental->has_distance_overage_payment;

        // servono km_in/km_out; se mancano, niente badge
        if (is_null($rental->mileage_out) || is_null($rental->mileage_in)) {
            return response()->json([
                'ok'          => true,
                'has_data'    => false,
                'cents'       => 0,
                'amount'      => '0.00',
                'has_payment' => $hasPayment, // ✅ sempre presente
            ]);
        }

        // listino attivo per il renter corrente del veicolo
        $pl = $svc->findActivePricelistForCurrentRenter($rental->vehicle);
        if (!$pl) {
            return response()->json([
                'ok'          => false,
                'message'     => 'Nessun listino attivo per il renter corrente.',
                'has_payment' => $hasPayment, // ✅ coerente anche in errore
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // giorni reali se disponibili, altrimenti pianificati
        $pickupAt  = $rental->actual_pickup_at  ?? $rental->planned_pickup_at  ?? now();
        $returnAt  = $rental->actual_return_at  ?? $rental->planned_return_at  ?? now();

        // km percorsi (min 0)
        $km = max(0, (int) $rental->mileage_in - (int) $rental->mileage_out);

        $quote  = $svc->quote($pl, $pickupAt, $returnAt, $km);
        $cents  = (int) ($quote['km_extra'] ?? 0);
        $amount = number_format($cents / 100, 2, '.', '');

        return response()->json([
            'ok'          => true,
            'has_data'    => true,
            'cents'       => $cents,
            'amount'      => $amount,
            'has_payment' => $hasPayment, // ✅ sempre presente
        ]);
    }
}
