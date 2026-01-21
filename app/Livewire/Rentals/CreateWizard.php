<?php

namespace App\Livewire\Rentals;

use Livewire\Component;
use Livewire\WithFileUploads;  
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\{Rental, Customer, Vehicle, Location, VehicleAssignment, CargosLuogo}; // Location esiste nel tuo schema (dalle query)
use App\Services\Contracts\GenerateRentalContract;
use App\Domain\Pricing\VehiclePricingService;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Rentals\RentalNumberAllocator;

class CreateWizard extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    /** Step corrente: 1..3 */
    public int $step = 1;

    /** Id bozza corrente (Rental) se già salvata */
    public ?int $rentalId = null;

    /** Campi rentals (nomi invariati) */
    public array $rentalData = [
        'vehicle_id'          => null,
        'pickup_location_id'  => null,
        'return_location_id'  => null,
        'planned_pickup_at'   => null,
        'planned_return_at'   => null,
        'notes'               => null,
        'final_amount_override' => null,
    ];

    /** Associazione cliente */
    public ?int $customer_id = null;

    /**
     * Form cliente (popolato alla selezione o per creazione).
     *
     * NOTE:
     * - Manteniamo le chiavi esistenti per compatibilità totale con l'UI attuale.
     * - Aggiungiamo le chiavi CARGOS per allinearci a Customers/Show senza rompere nulla.
     */
    public array $customerForm = [
        // =========================
        // Campi esistenti (NON toccare)
        // =========================
        'name'         => null,
        'email'        => null,
        'phone'        => null,
        'doc_id_type'  => null,
        'doc_id_number'=> null,
        'tax_code'     => null,
        'vat'          => null,
        'birth_date'   => null,
        'address'      => null,
        'city'         => null,
        'province'     => null,
        'zip'          => null,
        'country_code' => null,
        'driver_license_number'      => null,
        'driver_license_expires_at'  => null,

        // =========================
        // NUOVO: campi CARGOS (aggiuntivi)
        // =========================

        /** Nome per CARGOS (opzionale: può essere derivato da "name") */
        'first_name' => null,

        /** Cognome per CARGOS (opzionale: può essere derivato da "name") */
        'last_name'  => null,

        /** Codice CARGOS luogo di nascita (customer.birth_place_code) */
        'birth_place_code' => null,

        /** Codice CARGOS residenza (customer.police_place_code) */
        'police_place_code' => null,

        /**
         * Campo UI virtuale (come in Customers/Show):
         * - nello schema cliente è citizenship_cargos_code
         * - qui lo teniamo separato per bind diretto ai picker.
         */
        'citizenship_place_code' => null,

        /** Codice CARGOS tipo documento identità (string) */
        'identity_document_type_code' => null,

        /** Codice CARGOS luogo rilascio documento identità (place_code) */
        'identity_document_place_code' => null,

        /** Codice CARGOS luogo rilascio patente (place_code) */
        'driver_license_place_code' => null,
    ];

    /** Selezioni coperture (flags) */
    public array $coverage = [
        'kasko'           => true,
        'furto_incendio'  => true,
        'cristalli'       => true,
        'assistenza'      => true,
    ];

    /** Franchigie per coperture (valori in €) */
    public array $franchise = [
        'kasko'          => null,
        'furto_incendio' => null,
        'cristalli'      => null,
    ];

    /** Ricerca cliente */
    public string $customerQuery = '';

    /** Opzioni select */
    public $vehicles = [];
    public $locations = [];

    /** Se true, il form è stato popolato da un cliente selezionato */
    public bool $customerPopulated = false;

    /** Stato contratto generato (media corrente) */
    public ?int $currentContractMediaId = null;
    public ?string $currentContractUrl = null;

    /** Upload documenti preliminari (Livewire) */
    public ?string $docCollection = 'documents';
    public $docFile = null;

    /** Valori base franchigie (per UI) */
    public array $franchiseBase = [
        'rca'            => null,
        'kasko'          => null,
        'cristalli'      => null,
        'furto_incendio' => null,
    ];

    /**
     * Inizializza il wizard di creazione noleggio.
     *
     * - Carica le opzioni (veicoli, sedi) in base all'utente corrente.
     * - Se arriviamo dal planner (querystring con vehicle_id / planned_pickup_date/time),
     *   precompila i campi di step 1:
     *      - rentalData['vehicle_id']
     *      - rentalData['planned_pickup_at'] (data + ora)
     */
    public function mount(): void
    {
        // 1) Prelettura dei parametri dalla query (clic da planner)
        $qVehicleId      = request()->integer('vehicle_id');            // es: ?vehicle_id=12
        $qPickupDate     = request()->input('planned_pickup_date');     // es: ?planned_pickup_date=2025-11-17
        $qPickupTime     = request()->input('planned_pickup_time');     // es: ?planned_pickup_time=19:00 (solo vista giorno)

        // 2) Carica subito le opzioni (veicoli / sedi) in base all'utente
        $this->loadOptions();

        // 3) Se il planner ci ha passato un veicolo, lo impostiamo nel form
        if ($qVehicleId) {
            $this->rentalData['vehicle_id'] = $qVehicleId;

            // Riutilizziamo la tua logica esistente:
            // - sede di ritiro di default
            // - franchigie base dal veicolo
            $this->updatedRentalDataVehicleId($qVehicleId);
        }

        // 4) Se il planner ci ha passato un giorno (e opzionalmente un orario) di ritiro,
        //    precompiliamo il datetime del pickup
        if (!empty($qPickupDate)) {
            try {
                if (!empty($qPickupTime)) {
                    // Caso vista GIORNO: abbiamo anche l'ora (es. "2025-11-17 19:00")
                    $pickup = Carbon::parse($qPickupDate . ' ' . $qPickupTime);
                } else {
                    // Caso vista SETTIMANA (o fallback): solo data, usiamo un orario di default
                    $pickup = Carbon::parse($qPickupDate)->setTime(9, 0);
                }

                // Formato compatibile con <input type="datetime-local">: Y-m-dTH:i
                $this->rentalData['planned_pickup_at'] = $pickup->format('Y-m-d\TH:i');
            } catch (\Throwable $e) {
                // Se la data/ora è malformata non blocchiamo il wizard
                $this->rentalData['planned_pickup_at'] = null;
            }
        }

        // 5) Se esiste già una bozza ($rentalId impostato dall'esterno),
        //    rileggiamo l'eventuale contratto corrente
        $this->refreshCurrentContract();
    }

    /**
     * Cast “robusto” a int per codici CARGOS:
     * - accetta int/string numeriche
     * - ritorna null per valori vuoti/non numerici
     */
    protected function toIntOrNull(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }

        if (is_int($v)) {
            return $v;
        }

        if (is_string($v)) {
            $t = trim($v);
            if ($t === '' || !ctype_digit($t)) {
                return null;
            }

            return (int) $t;
        }

        if (is_numeric($v)) {
            return (int) $v;
        }

        return null;
    }

    /**
     * Rileggi l’ultimo contratto "valido" (custom_properties.current === true)
     * e popola URL + media_id per abilitare/disabilitare la UI.
     */
    public function refreshCurrentContract(): void
    {
        $this->currentContractMediaId = null;
        $this->currentContractUrl     = null;

        if (!$this->rentalId) return;

        $rental = Rental::find($this->rentalId);
        if (!$rental) return;

        $media = $rental->getMedia('contract')
            ->filter(fn($m) => (bool) $m->getCustomProperty('current') === true)
            ->sortByDesc('created_at')
            ->first();

        if ($media) {
            $this->currentContractMediaId = (int) $media->id;
            $this->currentContractUrl     = $media->getUrl();
        }
    }

    /** Carica veicoli e sedi secondo regole */
    protected function loadOptions(): void
    {
        // --- SEDI ---
        $this->locations = Location::query()
            ->where('organization_id', auth()->user()->organization_id)
            ->orderBy('name','asc')
            ->get(['id','name'])
            ->toArray();

        // --- VEICOLI ---
        $user   = Auth::user();
        $orgId  = $user->organization_id ?? null;
        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;

        $vehiclesQ = Vehicle::query()
            ->where('is_active', true)
            ->whereNull('deleted_at');

        /**
         * Filtro assegnazioni:
         * - Renter (non admin): mostra SOLO veicoli assegnati alla sua organizzazione
         * - Admin: mostra SOLO veicoli non assegnati a nessun renter (flotta libera)
         *
         * NB: le colonne/relazioni 'assignments', 'organization_id', 'end_at' sono
         *      dedotte dal tuo schema (vedi `rentals.assignment_id` nel DB). Se i nomi differiscono,
         *      adegua i whereHas/whereDoesntHave allo schema reale.
         */
        if ($isAdmin) {
            // Admin: solo veicoli NON assegnati a nessun renter (nessuna assegnazione attiva)
            $vehiclesQ->whereDoesntHave('assignments', fn($q) => $q->active());
        } else {
            // Renter: solo veicoli con assegnazione attiva alla sua org
            if ($orgId) {
                $vehiclesQ->whereHas('assignments', function ($q) use ($orgId) {
                    $q->active()->where('renter_org_id', $orgId);
                });
            } else {
                $vehiclesQ->whereRaw('1=0'); // nessun veicolo se non c’è org
            }
        }

        $this->vehicles = $vehiclesQ
            ->orderBy('id','desc')
            ->limit(200)
            ->get(['id','plate','make','model'])
            ->map(fn($v) => [
                'id'    => $v->id,
                'label' => $v->plate . ' — ' . $v->make . ' ' . $v->model ?: ('#'.$v->id),
            ])
            ->toArray();
    }

    /** Regole di validazione base per step 1 */
    protected function rulesStep1(): array
    {
        return [
            'rentalData.vehicle_id'         => ['required','integer','exists:vehicles,id'],
            'rentalData.pickup_location_id' => ['required','integer','exists:locations,id'],
            'rentalData.return_location_id' => ['required','integer','exists:locations,id'],
            'rentalData.planned_pickup_at'  => ['required','date'],
            'rentalData.planned_return_at'  => ['required','date','after_or_equal:rentalData.planned_pickup_at'],
            'rentalData.notes'              => ['nullable','string'],
            'coverage.rca'                  => ['boolean'], // sempre obbligatoria
            'coverage.kasko'                => ['boolean'],
            'coverage.furto_incendio'       => ['boolean'],
            'coverage.cristalli'            => ['boolean'],
            'coverage.assistenza'           => ['boolean'],
            'rentalData.final_amount_override' => ['nullable','numeric','min:0'],
            // Se una copertura è selezionata, la relativa franchigia può essere richiesta (qui la lasciamo facoltativa).
            'franchise.rca'                 => ['nullable','numeric','min:0'],
            'franchise.kasko'               => ['nullable','numeric','min:0'],
            'franchise.furto_incendio'      => ['nullable','numeric','min:0'],
            'franchise.cristalli'           => ['nullable','numeric','min:0'],
        ];
    }

    /**
     * Regole per creazione/aggiornamento cliente nello Wizard (Step 2).
     *
     * - Il "name" non è più input: viene costruito da first_name + last_name.
     * - Documento identità: solo CARGOS (identity_document_type_code + numero + luogo rilascio).
     * - Residenza: solo CARGOS (police_place_code) da cui deriviamo city/province/country_code e police_postal_code.
     */
    protected function rulesCustomerCreate(): array
    {
        return [
            // =========================
            // ✅ MINIMO OBBLIGATORIO
            // =========================
            'customerForm.first_name' => ['required', 'string', 'max:191'],
            'customerForm.last_name'  => ['required', 'string', 'max:191'],

            'customerForm.email' => ['required', 'email', 'max:191'],
            'customerForm.phone' => ['required', 'string', 'max:50'],

            // Residenza CARGOS: richiesta
            'customerForm.police_place_code' => ['required', 'integer', 'exists:cargos_luoghi,code'],

            // =========================
            // ✅ CARGOS - Anagrafica (opzionale)
            // =========================
            'customerForm.birth_date'      => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'customerForm.birth_place_code'=> ['nullable', 'integer', 'exists:cargos_luoghi,code'],

            // Cittadinanza: picker country-only (salviamo il code nazione)
            'customerForm.citizenship_place_code' => ['nullable', 'integer', 'exists:cargos_luoghi,code'],

            // =========================
            // ✅ Documento identità (solo CARGOS)
            // =========================
            'customerForm.identity_document_type_code' => ['nullable', 'string', 'exists:cargos_document_types,code'],
            'customerForm.doc_id_number'               => ['nullable', 'string', 'max:100'],
            'customerForm.identity_document_place_code'=> ['nullable', 'integer', 'exists:cargos_luoghi,code'],

            // =========================
            // ✅ Fiscali (opzionali)
            // =========================
            'customerForm.tax_code' => ['nullable', 'string', 'max:32'],
            'customerForm.vat'      => ['nullable', 'string', 'max:32'],

            // =========================
            // ✅ Patente (opzionali)
            // =========================
            'customerForm.driver_license_number'     => ['nullable', 'string', 'max:64'],
            'customerForm.driver_license_expires_at' => ['nullable', 'date', 'after:1900-01-01'],
            'customerForm.driver_license_place_code' => ['nullable', 'integer', 'exists:cargos_luoghi,code'],

            // =========================
            // ✅ Indirizzo testuale (opzionale)
            // =========================
            'customerForm.address' => ['nullable', 'string', 'max:255'],

            // CAP "manuale" opzionale: se lo compiliamo noi via CARGOS, non è obbligatorio
            'customerForm.zip' => ['nullable', 'string', 'max:20'],

            // country_code NON è più input “affidabile”: lo deriviamo da CARGOS (ma lo lasciamo nullable se ancora presente in UI)
            'customerForm.country_code' => ['nullable', 'string', 'size:2'],

            // ⚠️ Rimossi:
            // - customerForm.name (non più input)
            // - customerForm.doc_id_type (non più input: derivato dal codice CARGOS identity_document_type_code)
            // - customerForm.city / province (derivati da police_place_code)
        ];
    }

    /**
     * Messaggi di validazione personalizzati per lo STEP 1.
     * Chiavi: "<campo>.<regola>"
     */
    protected function messages(): array
    {
        return [
            // rentalData.*
            'rentalData.vehicle_id.required'         => 'Seleziona un veicolo.',
            'rentalData.vehicle_id.exists'           => 'Il veicolo selezionato non è valido.',
            'rentalData.pickup_location_id.required' => 'Seleziona la sede di ritiro.',
            'rentalData.pickup_location_id.exists'   => 'La sede di ritiro non è valida.',
            'rentalData.return_location_id.required' => 'Seleziona la sede di riconsegna.',
            'rentalData.return_location_id.exists'   => 'La sede di riconsegna non è valida.',

            'rentalData.planned_pickup_at.required'  => 'Inserisci data e ora di ritiro.',
            'rentalData.planned_pickup_at.date'      => 'La data di ritiro non è valida.',
            'rentalData.planned_return_at.required'  => 'Inserisci data e ora di riconsegna.',
            'rentalData.planned_return_at.date'      => 'La data di riconsegna non è valida.',
            'rentalData.planned_return_at.after_or_equal' => 'La riconsegna deve essere successiva o uguale al ritiro.',

            // coverage.* (checkbox)
            'coverage.rca.boolean'             => 'Selezione non valida per RCA.',
            'coverage.kasko.boolean'           => 'Selezione non valida per Kasko.',
            'coverage.furto_incendio.boolean'  => 'Selezione non valida per Furto/Incendio.',
            'coverage.cristalli.boolean'       => 'Selezione non valida per Cristalli.',
            'coverage.assistenza.boolean'      => 'Selezione non valida per Assistenza.',

            // franchise.* (override importi)
            'franchise.rca.numeric'            => 'La franchigia RCA deve essere un importo valido.',
            'franchise.rca.min'                => 'La franchigia RCA non può essere negativa.',
            'franchise.kasko.numeric'          => 'La franchigia Kasko deve essere un importo valido.',
            'franchise.kasko.min'              => 'La franchigia Kasko non può essere negativa.',
            'franchise.furto_incendio.numeric' => 'La franchigia Furto/Incendio deve essere un importo valido.',
            'franchise.furto_incendio.min'     => 'La franchigia Furto/Incendio non può essere negativa.',
            'franchise.cristalli.numeric'      => 'La franchigia Cristalli deve essere un importo valido.',
            'franchise.cristalli.min'          => 'La franchigia Cristalli non può essere negativa.',

            // final_amount_override
            'rentalData.final_amount_override.numeric' => 'Il prezzo finale deve essere un importo valido.',
            'rentalData.final_amount_override.min'     => 'Il prezzo finale non può essere negativo.',
        ];
    }

    /**
     * Label "umane" dei campi, usate per comporre i messaggi.
     */
    protected function validationAttributes(): array
    {
        return [
            'rentalData.vehicle_id'         => 'veicolo',
            'rentalData.pickup_location_id' => 'sede di ritiro',
            'rentalData.return_location_id' => 'sede di riconsegna',
            'rentalData.planned_pickup_at'  => 'ritiro pianificato',
            'rentalData.planned_return_at'  => 'riconsegna pianificata',
            'coverage.rca'                  => 'RCA base',
            'coverage.kasko'                => 'Kasko',
            'coverage.furto_incendio'       => 'Furto/Incendio',
            'coverage.cristalli'            => 'Cristalli',
            'coverage.assistenza'           => 'Assistenza',
            'franchise.rca'                 => 'franchigia RCA',
            'franchise.kasko'               => 'franchigia Kasko',
            'franchise.furto_incendio'      => 'franchigia Furto/Incendio',
            'franchise.cristalli'           => 'franchigia Cristalli',
            'rentalData.final_amount_override' => 'prezzo finale',
        ];
    }

    /** Salva/aggiorna la bozza (sempre status=draft) */
    public function saveDraft(): void
    {
        $this->validate(
            $this->rulesStep1(),
            $this->messages(),
            $this->validationAttributes()
        );

        $orgId = auth()->user()->organization_id;

        if (!$orgId) {
            throw new \RuntimeException('Organization ID mancante durante la creazione del rental');
        }

        // recupero l'assegnazione del veicolo selezionato
        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', $this->rentalData['vehicle_id'] ?? 0)
            ->active()
            ->latest('start_at')
            ->first();

        // final_amount_override (se vuoto → null)
        $override = $this->rentalData['final_amount_override'] ?? null;
        $override = (is_string($override) && trim($override) === '') ? null : $override;
        $finalAmountOverride = is_null($override) ? null : round((float) $override, 2);

        // Cast date una volta sola (evita parse ripetuti)
        $plannedPickupAt = $this->castDate($this->rentalData['planned_pickup_at'] ?? null);
        $plannedReturnAt = $this->castDate($this->rentalData['planned_return_at'] ?? null);

        /**
         * 💶 Denormalizzazione importo base del noleggio su rentals.amount
         * - calcolata prima, poi assegnata al model (create o update)
         */
        $amountEuro = null;

        try {
            if (($this->rentalData['vehicle_id'] ?? null) && $plannedPickupAt && $plannedReturnAt) {

                $vehicle = Vehicle::query()->find($this->rentalData['vehicle_id']);

                if ($vehicle) {
                    /** @var VehiclePricingService $pricing */
                    $pricing = app(VehiclePricingService::class);

                    $pl = $pricing->findActivePricelistForCurrentRenter($vehicle);

                    if ($pl) {
                        $expectedKm = (int) ($this->expectedKm ?? 0);

                        $quote = $pricing->quote(
                            $pl,
                            $plannedPickupAt,
                            $plannedReturnAt,
                            $expectedKm
                        );

                        $totalCents = (int) ($quote['total'] ?? 0);
                        $amountEuro = round($totalCents / 100, 2);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
            // Non blocchiamo la bozza: amount resta invariato
        }

        /**
         * ✅ CREATE: se non esiste ancora un rental, lo creiamo tramite allocatore
         * (lock su organizations + progressivo per organization_id + insert audit ledger).
         */
        if (!$this->rentalId) {

            /** @var RentalNumberAllocator $allocator */
            $allocator = app(RentalNumberAllocator::class);

            $rental = $allocator->allocateAndCreate(
                $orgId,
                (int) auth()->id(),
                function (int $numberId) use ($assignment, $plannedPickupAt, $plannedReturnAt, $finalAmountOverride, $amountEuro) {

                    $rental = new Rental();

                    // Campi (nomi invariati)
                    $rental->vehicle_id         = $this->rentalData['vehicle_id'] ?? null;
                    $rental->pickup_location_id = $this->rentalData['pickup_location_id'] ?? null;
                    $rental->return_location_id = $this->rentalData['return_location_id'] ?? null;
                    $rental->planned_pickup_at  = $plannedPickupAt;
                    $rental->planned_return_at  = $plannedReturnAt;

                    $rental->organization_id    = auth()->user()->organization_id;
                    $rental->assignment_id      = $assignment->id ?? null;

                    $rental->notes              = $this->rentalData['notes'] ?? null;
                    $rental->final_amount_override = $finalAmountOverride;

                    // ✅ Numero progressivo per noleggiatore (NUOVO)
                    $rental->number_id = $numberId;

                    // Cliente (se già scelto)
                    if ($this->customer_id) {
                        $rental->customer_id = $this->customer_id;
                    }

                    // amount (se calcolato)
                    if (!is_null($amountEuro)) {
                        $rental->amount = $amountEuro;
                    }

                    // Bozza
                    $rental->status = 'draft';

                    $rental->save();

                    return $rental;
                }
            );

            $this->rentalId = $rental->id;

        } else {

            /**
             * ✅ UPDATE: rental già esistente → aggiorniamo normalmente
             * (number_id non si tocca mai qui)
             */
            $rental = Rental::query()->findOrFail($this->rentalId);

            $rental->vehicle_id         = $this->rentalData['vehicle_id'] ?? null;
            $rental->pickup_location_id = $this->rentalData['pickup_location_id'] ?? null;
            $rental->return_location_id = $this->rentalData['return_location_id'] ?? null;
            $rental->planned_pickup_at  = $plannedPickupAt;
            $rental->planned_return_at  = $plannedReturnAt;

            $rental->organization_id    = auth()->user()->organization_id;
            $rental->assignment_id      = $assignment->id ?? null;

            $rental->notes              = $this->rentalData['notes'] ?? null;
            $rental->final_amount_override = $finalAmountOverride;

            if ($this->customer_id) {
                $rental->customer_id = $this->customer_id;
            }

            if (!is_null($amountEuro)) {
                $rental->amount = $amountEuro;
            }

            if ($rental->status === 'draft' || $rental->status === null) {
                $rental->status = 'draft';
            }

            $rental->save();
        }

        // === Coverage 1:1 (creazione idempotente) ===============================
        $rental->coverage()->firstOrCreate(
            ['rental_id' => $rental->id],
            [
                'rca'                        => true,
                'kasko'                      => $this->coverage['kasko'] ?? false,
                'furto_incendio'             => $this->coverage['furto_incendio'] ?? false,
                'cristalli'                  => $this->coverage['cristalli'] ?? false,
                'assistenza'                 => $this->coverage['assistenza'] ?? false,
                'franchise_rca'              => $this->franchise['rca'] ?? null,
                'franchise_kasko'            => $this->franchise['kasko'] ?? null,
                'franchise_furto_incendio'   => $this->franchise['furto_incendio'] ?? null,
                'franchise_cristalli'        => $this->franchise['cristalli'] ?? null,
            ]
        );
        // =======================================================================

        $this->dispatch('toast', type:'success', message:'Bozza salvata.');
    }

    /** Avanti di step (salviamo in 1 → 2 per avere l’ID bozza) */
    public function next(): void
    {
        if ($this->step === 1) {
            // check overlap veicolo
            $this->assertVehicleAvailability();
            // validazioni base + salvataggio bozza
            $this->saveDraft();
        }

        if ($this->step === 2) {

            // ✅ Vincolo di flusso: non puoi arrivare allo step 3 senza aver associato un cliente
            if (!$this->customer_id) {
                $this->dispatch('toast', type: 'error', message: 'Seleziona o crea un cliente prima di procedere.');

                // Livewire: blocca l'avanzamento e mostra errore sul campo logico "customer_id"
                throw ValidationException::withMessages([
                    'customer_id' => 'Devi selezionare o creare un cliente per procedere allo step 3.',
                ]);
            }

            // se ha selezionato o creato un cliente, controlla overlap sul cliente
            $this->assertCustomerNoOverlap();

            // ✅ BLOCCO patente: deve coprire tutto il periodo fino alla riconsegna
            $this->assertDriverLicenseValidThroughReturn();

            // salva comunque per avere stato coerente
            $this->saveDraft();
        }

        if ($this->step < 3) {
            $this->dispatch('toast', type: 'info', message: 'Step salvato, puoi procedere.');
            $this->step++;
        }
    }

    public function prev(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    /** Selezione cliente: popola il form e collega alla bozza */
    public function selectCustomer(int $id): void
    {
        $customer = Customer::query()->findOrFail($id);

        $this->customer_id = $customer->id;

        // fallback: se mancano first/last ma esiste name
        $fn = $customer->first_name;
        $ln = $customer->last_name;

        if ((!$fn && !$ln) && $customer->name) {
            [$fn2, $ln2] = $this->splitFullName($customer->name);
            $fn = $fn2;
            $ln = $ln2;
        }

        $this->customerForm = array_merge($this->customerForm, [
            // anagrafica
            'first_name'   => $fn,
            'last_name'    => $ln,
            'name'         => $customer->name, // lo teniamo come compatibilità/preview
            'birth_date'   => optional($customer->birthdate)->format('Y-m-d'),

            // contatti
            'email'        => $customer->email,
            'phone'        => $customer->phone,

            // documenti
            'doc_id_number'                => $customer->doc_id_number,
            'doc_id_type'                  => $customer->doc_id_type, // interno (compatibilità)
            'identity_document_type_code'  => $customer->identity_document_type_code,
            'identity_document_place_code' => $customer->identity_document_place_code,

            'driver_license_number'             => $customer->driver_license_number,
            'driver_license_expires_at'         => optional($customer->driver_license_expires_at)->format('Y-m-d'),
            'driver_license_place_code'         => $customer->driver_license_place_code,
            'driver_license_document_type_code' => $customer->driver_license_document_type_code ?? 'PATEN',

            // CARGOS luoghi
            'birth_place_code'      => $customer->birth_place_code,
            'police_place_code'     => $customer->police_place_code,
            'citizenship_place_code'=> $customer->citizenship_cargos_code, // il picker usa il code del luogo

            // indirizzo testuale (se lo stai ancora mostrando)
            'address'      => $customer->address_line,
            'zip'          => $customer->postal_code,
            'country_code' => $customer->country_code,

            // fiscali
            'tax_code'     => $customer->tax_code,
            'vat'          => $customer->vat,
        ]);

        $this->customerPopulated = true;

        // Collega alla bozza
        $this->saveDraft();
    }

    /**
     * Crea o aggiorna il cliente in base ai dati del form.
     */
    public function createOrUpdateCustomer(): void
    {
        $this->validate($this->rulesCustomerCreate());

        $orgId = auth()->user()->organization_id;

        if (!$orgId) {
            throw new \RuntimeException('Organization ID mancante durante la creazione del cliente');
        }

        // =========
        // 1) Nome: costruito da first+last (con fallback su "name" se serve)
        // =========
        $firstName = $this->nullIfBlank($this->customerForm['first_name'] ?? null);
        $lastName  = $this->nullIfBlank($this->customerForm['last_name'] ?? null);

        if ((!$firstName && !$lastName) && !empty($this->customerForm['name'])) {
            [$fn, $ln] = $this->splitFullName($this->customerForm['name']);
            $firstName = $firstName ?: $fn;
            $lastName  = $lastName  ?: $ln;
        }

        $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        $fullName = $fullName !== '' ? $fullName : $this->nullIfBlank($this->customerForm['name'] ?? null);

        // =========
        // 2) CARGOS: doc type + mapping doc_id_type interno
        // =========
        $identityDocCargos = $this->nullIfBlank($this->customerForm['identity_document_type_code'] ?? null);

        // doc_id_type interno:
        // - se arriva già dal form (compatibilità vecchia UI) lo teniamo
        // - altrimenti lo deriviamo dal codice CARGOS
        $internalDocType = $this->nullIfBlank($this->customerForm['doc_id_type'] ?? null)
            ?? $this->mapCargosDocTypeToInternal($identityDocCargos);

        // =========
        // 3) Luoghi CARGOS (nascita/residenza/cittadinanza)
        // =========
        $birthPlaceCode      = !empty($this->customerForm['birth_place_code']) ? (int) $this->customerForm['birth_place_code'] : null;
        $policePlaceCode     = !empty($this->customerForm['police_place_code']) ? (int) $this->customerForm['police_place_code'] : null;
        $citizenshipPlaceCode= !empty($this->customerForm['citizenship_place_code']) ? (int) $this->customerForm['citizenship_place_code'] : null;

        $birthPlaceName = null;
        if ($birthPlaceCode) {
            $luogo = CargosLuogo::find($birthPlaceCode);
            $birthPlaceName = $luogo?->name;
        }

        $citizenshipName = null;
        if ($citizenshipPlaceCode) {
            $luogo = CargosLuogo::find($citizenshipPlaceCode);
            $citizenshipName = $luogo?->name;
        }

        // Residenza: deriva city/province/country_code + police_postal_code
        $resDerived = $this->deriveResidenceFromPolicePlaceCode($policePlaceCode);

        // =========
        // 4) Payload DB completo (include tutti i campi che ti mancavano)
        // =========
        $payload = [
            'organization_id' => $orgId,

            // anagrafica
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => $fullName,

            'birthdate'  => $this->castDate($this->customerForm['birth_date'] ?? null),
            'birth_place_code' => $birthPlaceCode,
            'birth_place'      => $birthPlaceName,

            // contatti
            'email' => $this->nullIfBlank($this->customerForm['email'] ?? null),
            'phone' => $this->nullIfBlank($this->customerForm['phone'] ?? null),

            // fiscali
            'tax_code' => $this->normalizeTaxCode($this->customerForm['tax_code'] ?? null),
            'vat'      => $this->normalizeVat($this->customerForm['vat'] ?? null),

            // documenti identità (CARGOS only + numero)
            'identity_document_type_code'  => $identityDocCargos,
            'doc_id_type'                  => $internalDocType,
            'doc_id_number'                => $this->nullIfBlank($this->customerForm['doc_id_number'] ?? null),
            'identity_document_place_code' => !empty($this->customerForm['identity_document_place_code'])
                ? (int) $this->customerForm['identity_document_place_code']
                : null,

            // patente
            'driver_license_document_type_code' => 'PATEN',
            'driver_license_number'             => $this->nullIfBlank($this->customerForm['driver_license_number'] ?? null),
            'driver_license_expires_at'         => $this->castDate($this->customerForm['driver_license_expires_at'] ?? null),
            'driver_license_place_code'         => !empty($this->customerForm['driver_license_place_code'])
                ? (int) $this->customerForm['driver_license_place_code']
                : null,

            // residenza CARGOS
            'police_place_code' => $policePlaceCode,

            // indirizzo testuale (se lo stai ancora usando nello wizard)
            'address_line' => $this->nullIfBlank($this->customerForm['address'] ?? null),
            'postal_code'  => $this->nullIfBlank($this->customerForm['zip'] ?? null),

            // campi derivati dalla residenza CARGOS
            'city'               => $resDerived['city'],
            'province'           => $resDerived['province'],
            'country_code'       => $this->nullIfBlank($this->customerForm['country_code'] ?? null) ?? $resDerived['country_code'],
            'police_postal_code' => $resDerived['police_postal_code'],

            // cittadinanza
            'citizenship'            => $citizenshipName,
            'citizenship_cargos_code'=> $citizenshipPlaceCode,
        ];

        // =========
        // 5) CREATE / UPDATE
        // =========
        if ($this->customer_id) {
            $customer = Customer::query()->findOrFail($this->customer_id);
            $customer->update($payload);
        } else {
            $customer = Customer::query()->create($payload);
            $this->customer_id = $customer->id;
        }

        $this->customerPopulated = true;

        // ricollega alla bozza
        $this->saveDraft();
    }

    /**
     * Ritorna null se stringa vuota, altrimenti trimmed.
     */
    protected function nullIfBlank(?string $v): ?string
    {
        if ($v === null) return null;
        $t = trim($v);
        return $t === '' ? null : $t;
    }

    /**
     * Mappa i codici CARGOS dei tipi documento ai valori interni usati nell'app.
     */
    private function mapCargosDocTypeToInternal(?string $cargosCode): ?string
    {
        return match ($cargosCode) {
            'IDENT', 'IDELE'                 => 'id',
            'PASDI', 'PASOR', 'PASSE'        => 'passport',
            'CIDIP'                          => 'other',
            default                          => null,
        };
    }

    /**
     * Deriva campi "testuali" dalla residenza CARGOS (police_place_code):
     * - city, province, country_code
     * - police_postal_code (se presente nel raw_payload del luogo)
     *
     * NB: Se il place è una NAZIONE (province_code === 'ES'), city/province restano null.
     */
    private function deriveResidenceFromPolicePlaceCode(?int $policePlaceCode): array
    {
        $out = [
            'city'               => null,
            'province'           => null,
            'country_code'       => null,
            'police_postal_code' => null,
        ];

        if (!$policePlaceCode) return $out;

        /** @var CargosLuogo|null $luogo */
        $luogo = CargosLuogo::find($policePlaceCode);
        if (!$luogo) return $out;

        // NAZIONE (estero o Italia "come nazione"): province_code = 'ES'
        if ($luogo->province_code === 'ES') {
            $cc = $luogo->country_code;
            $out['country_code'] = is_string($cc) && $cc !== '' ? substr($cc, 0, 2) : null;
            return $out;
        }

        // COMUNE (Italia)
        $out['city']         = $luogo->name;
        $out['province']     = $luogo->province_code;
        $out['country_code'] = 'IT';

        // Prova a recuperare CAP da raw_payload (chiavi variabili a seconda del dataset)
        $raw = is_array($luogo->raw_payload) ? $luogo->raw_payload : [];
        $cap = data_get($raw, 'cap')
            ?? data_get($raw, 'CAP')
            ?? data_get($raw, 'postal_code')
            ?? data_get($raw, 'zip')
            ?? null;

        $out['police_postal_code'] = is_string($cap) && trim($cap) !== '' ? trim($cap) : null;

        return $out;
    }

    /**
     * Ritorna [first,last] a partire da un "name" pieno (fallback).
     */
    private function splitFullName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') return [null, null];

        [$fn, $ln] = array_pad(explode(' ', $fullName, 2), 2, null);

        $fn = $fn !== null ? trim($fn) : null;
        $ln = $ln !== null ? trim($ln) : null;

        return [$fn ?: null, $ln ?: null];
    }

    /**
     * Normalizza Codice Fiscale:
     * - trim, rimuove spazi, uppercase
     */
    protected function normalizeTaxCode(?string $v): ?string
    {
        if ($v === null) return null;

        $norm = strtoupper(preg_replace('/\s+/', '', trim($v)));

        return $norm !== '' ? $norm : null;
    }

    /**
     * Normalizza Partita IVA:
     * - trim, rimuove spazi
     */
    protected function normalizeVat(?string $v): ?string
    {
        if ($v === null) return null;

        $norm = preg_replace('/\s+/', '', trim($v));

        return $norm !== '' ? $norm : null;
    }


    /** Genera il contratto (step 3) */
    public function generateContract(GenerateRentalContract $service): void
    {
        if (!$this->rentalId) {
            $this->dispatch('toast', type: 'error', message: 'Salva la bozza prima di generare il contratto.');
            return;
        }

        $rental = Rental::query()->findOrFail($this->rentalId);
        $this->authorize('contractGenerate', $rental);

        try {
            $service->handle(
                $rental,
                $this->coverage ?? null,
                $this->franchise ?? null,
                (int)($this->expectedKm ?? 0)
            );

            /**
             * ✅ Dopo generazione contratto: la bozza diventa "reserved"
             * Non forziamo altri stati: se non è draft, non tocchiamo nulla.
             */
            if ($rental->status === 'draft') {
                $rental->status = 'reserved';
                $rental->save();
            }

            // Ricarica stato contratto per mostrare "Apri" e disabilitare "Genera"
            $this->refreshCurrentContract();

            $this->dispatch('toast', type: 'success', message: 'Contratto generato e salvato.');
            // Se vuoi, ricarica solo un pannello/pezzo della pagina:
            // $this->dispatch('refresh-contract-panel');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch(
                'toast',
                type: 'error',
                message: config('app.debug') ? $e->getMessage() : 'Errore durante la generazione del contratto.'
            );
        }
    }

    /** Fine wizard → vai alla show */
    public function finish(): void
    {
        if (!$this->rentalId) {
            $this->saveDraft();
        }
        redirect()->route('rentals.show', $this->rentalId);
    }

    /** Helper: cast string->Carbon o null */
    protected function castDate(?string $v): ?Carbon
    {
        if (empty($v)) return null;
        try { return Carbon::parse($v); } catch (\Throwable) { return null; }
    }

    /**
     * Quando l'utente seleziona un veicolo, popoliamo automaticamente la sede di ritiro
     * in base alla *sede attuale* del veicolo. Usiamo più fallback per adattarci allo schema:
     * - $vehicle->default_pickup_location_id
     * - $vehicle->current_location_id
     * - $vehicle->location?->id (relazione)
     * - $vehicle->pickup_location_id (se lo usate così)
     */
    public function updatedRentalDataVehicleId($vehicleId): void
    {
        if ($vehicleId === null) {
            $this->rentalData['pickup_location_id'] = '';
            // pulizia base quando si deseleziona
            $this->franchiseBase = ['rca'=>null,'kasko'=>null,'cristalli'=>null,'furto_incendio'=>null];
            return;
        }

        $vehicle = Vehicle::query()->find($vehicleId);
        if (!$vehicle) return;

        // sede pickup (già esistente)
        $currentLocationId =
            $vehicle->default_pickup_location_id
            ?? $vehicle->current_location_id
            ?? optional($vehicle->location)->id
            ?? $vehicle->pickup_location_id
            ?? null;

        if ($currentLocationId) {
            $this->rentalData['pickup_location_id'] = $currentLocationId;
        }

        // NUOVO: “base” delle franchigie lette dal veicolo (in euro)
        $toEuro = fn($cents) => is_null($cents) ? null : round($cents / 100, 2);

        $this->franchiseBase = [
            'rca'            => $toEuro($vehicle->insurance_rca_cents      ?? null),
            'kasko'          => $toEuro($vehicle->insurance_kasko_cents    ?? null),
            'cristalli'      => $toEuro($vehicle->insurance_cristalli_cents?? null),
            'furto_incendio' => $toEuro($vehicle->insurance_furto_cents    ?? null),
        ];
    }

    /**
     * Verifica sovrapposizioni per lo stesso veicolo nelle date scelte.
     * Overlap rule: A.start < B.end && A.end > B.start
     *
     * Esclusioni:
     * - status in ['cancelled', 'no_show'] NON blocca nuove prenotazioni.
     */
    protected function assertVehicleAvailability(): void
    {
        $vehicleId = $this->rentalData['vehicle_id'] ?? null;
        $start     = $this->castDate($this->rentalData['planned_pickup_at'] ?? null);
        $end       = $this->castDate($this->rentalData['planned_return_at'] ?? null);

        if (!$vehicleId || !$start || !$end) return;

        /**
         * Stati da ignorare per l'overlap.
         * NB: presuppone che in DB siano salvati come stringhe tipo 'cancelled' / 'no_show'.
         */
        $ignoreStatuses = ['cancelled', 'no_show'];

        $exists = Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->when($this->rentalId, fn(Builder $q) => $q->where('id','!=',$this->rentalId))
            // ✅ Ignora noleggi annullati / no-show
            ->whereNotIn('status', $ignoreStatuses)
            // Overlap interval
            ->where(function (Builder $q) use ($start, $end) {
                $q->where('planned_pickup_at', '<', $end)
                ->where('planned_return_at', '>', $start);
            })
            ->exists();

        if ($exists) {
            $this->dispatch('toast', type: 'error', message: 'Il veicolo è già prenotato per le date selezionate.');
            throw ValidationException::withMessages([
                'rentalData.vehicle_id' => 'Il veicolo selezionato risulta già prenotato nelle date indicate.',
                'rentalData.planned_return_at' => 'Intervallo non disponibile per questo veicolo.',
            ]);
        }
    }

    /**
     * Verifica che lo stesso cliente non abbia già una prenotazione nello stesso periodo.
     *
     * Esclusioni:
     * - status in ['cancelled', 'no_show'] NON blocca nuove prenotazioni.
     */
    protected function assertCustomerNoOverlap(): void
    {
        $customerId = $this->customer_id;
        $start      = $this->castDate($this->rentalData['planned_pickup_at'] ?? null);
        $end        = $this->castDate($this->rentalData['planned_return_at'] ?? null);

        if (!$customerId || !$start || !$end) return;

        /** Stati da ignorare per l'overlap (vedi nota nel metodo veicolo). */
        $ignoreStatuses = ['cancelled', 'no_show'];

        $exists = Rental::query()
            ->where('customer_id', $customerId)
            ->when($this->rentalId, fn(Builder $q) => $q->where('id','!=',$this->rentalId))
            // ✅ Ignora noleggi annullati / no-show
            ->whereNotIn('status', $ignoreStatuses)
            ->where(function (Builder $q) use ($start, $end) {
                $q->where('planned_pickup_at', '<', $end)
                ->where('planned_return_at', '>', $start);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'customerForm.name' => 'Questo cliente ha già una prenotazione che si sovrappone al periodo selezionato.',
            ]);
        }
    }

    /**
     * Verifica che la patente sia valida fino alla fine del noleggio.
     * Regola: il noleggio può terminare lo stesso giorno della scadenza (valida fino a fine giornata).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function assertDriverLicenseValidThroughReturn(): void
    {
        // Data/ora di riconsegna pianificata
        $end = $this->castDate($this->rentalData['planned_return_at'] ?? null);

        // Se non ho una riconsegna, non posso validare
        if (!$end) {
            return;
        }

        // Se non ho scadenza patente, in questa fase non blocchiamo
        $expires = $this->castDate($this->customerForm['driver_license_expires_at'] ?? null);
        if (!$expires) {
            return;
        }

        $expiresEndOfDay = $expires->copy()->endOfDay();

        if ($end->greaterThan($expiresEndOfDay)) {
            $this->dispatch('toast', type: 'error', message: 'La patente del cliente risulta scadere prima della fine del noleggio.');

            throw ValidationException::withMessages([
                'customerForm.driver_license_expires_at' => 'La patente deve essere valida almeno fino alla data/ora di riconsegna.',
                'rentalData.planned_return_at'           => 'La riconsegna non può essere successiva alla scadenza della patente.',
            ]);
        }
    }

    public function render()
    {
        // Sorgente risultati ricerca clienti (semplice, da raffinare con debounce lato view)
        $customers = [];
        if (mb_strlen($this->customerQuery) >= 2) {
            $customers = Customer::query()
                ->where('name','like','%'.$this->customerQuery.'%')
                ->orWhere('driver_license_number','like','%'.$this->customerQuery.'%')
                ->limit(10)
                ->get(['id','name','driver_license_number'])
                ->toArray();
        }

        return view('livewire.rentals.create-wizard', [
            'customers' => $customers,
        ]);
    }
}
