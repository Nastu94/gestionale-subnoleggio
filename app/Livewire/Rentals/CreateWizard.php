<?php

namespace App\Livewire\Rentals;

use Livewire\Component;
use Livewire\WithFileUploads;  
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\{Rental, Customer, Vehicle, Location, VehicleAssignment}; // Location esiste nel tuo schema (dalle query)
use App\Services\Contracts\GenerateRentalContract;
use App\Domain\Pricing\VehiclePricingService;
use Illuminate\Database\Eloquent\Builder;

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
    ];

    /** Associazione cliente */
    public ?int $customer_id = null;

    /** Form cliente (popolato alla selezione o per creazione) */
    public array $customerForm = [
        'name'         => null,
        'email'         => null,
        'phone'         => null,
        'doc_id_type'   => null,
        'doc_id_number' => null,
        'birth_date'    => null,
        'address'       => null,
        'city'          => null,
        'province'      => null,
        'zip'           => null,
        'country_code'  => null,
        'driver_license_number'       => null,
        'driver_license_expires_at'   => null,
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

            // Se una copertura è selezionata, la relativa franchigia può essere richiesta (qui la lasciamo facoltativa).
            'franchise.rca'                 => ['nullable','numeric','min:0'],
            'franchise.kasko'               => ['nullable','numeric','min:0'],
            'franchise.furto_incendio'      => ['nullable','numeric','min:0'],
            'franchise.cristalli'           => ['nullable','numeric','min:0'],
        ];
    }

    /** Regole per creazione/aggiornamento cliente */
    protected function rulesCustomerCreate(): array
    {
        return [
            'customerForm.name'          => ['required','string','max:255'],
            'customerForm.email'         => ['required','email','max:255'],
            'customerForm.phone'         => ['required','string','max:50'],
            'customerForm.doc_id_type'   => ['required','in:id,passport'],
            'customerForm.doc_id_number' => ['required','string','max:100'],
            'customerForm.birth_date'    => ['required','date'],
            'customerForm.address'       => ['required','string','max:255'],
            'customerForm.city'          => ['required','string','max:100'],
            'customerForm.province'      => ['required','string','max:10'],
            'customerForm.zip'           => ['required','string','max:20'],
            'customerForm.country_code'  => ['required','string','max:2'],
            'customerForm.driver_license_number'      => ['required','string','max:64'],
            'customerForm.driver_license_expires_at'  => ['required','date'],
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

        $rental = $this->rentalId
            ? Rental::query()->findOrFail($this->rentalId)
            : new Rental();


        // recupero l'assegnazione del veicolo selezionato
        $assignment = VehicleAssignment::query()
            ->where('vehicle_id', $this->rentalData['vehicle_id'] ?? 0)
            ->active()
            ->latest('start_at')
            ->first();

        // Applica i campi (nomi invariati)
        $rental->vehicle_id         = $this->rentalData['vehicle_id'] ?? null;
        $rental->pickup_location_id = $this->rentalData['pickup_location_id'] ?? null;
        $rental->return_location_id = $this->rentalData['return_location_id'] ?? null;
        $rental->planned_pickup_at  = $this->castDate($this->rentalData['planned_pickup_at'] ?? null);
        $rental->planned_return_at  = $this->castDate($this->rentalData['planned_return_at'] ?? null);
        $rental->organization_id    = auth()->user()->organization_id;
        $rental->assignment_id      = $assignment->id ?? null;
        $rental->notes              = $this->rentalData['notes'] ?? null;

        if ($this->customer_id) {
            $rental->customer_id = $this->customer_id;
        }

        /**
         * 💶 Denormalizzazione importo base del noleggio su rentals.amount
         * - Serve per precompilare il modale "Quota base" senza inserimento manuale.
         * - Usiamo VehiclePricingService::quote() e prendiamo solo 'total' (in centesimi).
         * - Se manca listino o dati, NON blocchiamo il wizard: lasciamo amount invariato.
         */
        try {
            if ($rental->vehicle_id && $rental->planned_pickup_at && $rental->planned_return_at) {

                // Recupero veicolo (serve per trovare il listino attivo del renter corrente)
                $vehicle = Vehicle::query()->find($rental->vehicle_id);

                if ($vehicle) {
                    /** @var VehiclePricingService $pricing */
                    $pricing = app(VehiclePricingService::class);

                    // Listino attivo per il renter corrente
                    $pl = $pricing->findActivePricelistForCurrentRenter($vehicle);

                    if ($pl) {
                        // Km previsti: se la property non è ancora impostata, fallback a 0
                        $expectedKm = (int) ($this->expectedKm ?? 0);

                        // Preventivo: total è in cents
                        $quote = $pricing->quote(
                            $pl,
                            $rental->planned_pickup_at,
                            $rental->planned_return_at,
                            $expectedKm
                        );

                        $totalCents = (int) ($quote['total'] ?? 0);

                        // Salviamo in euro su campo DECIMAL(10,2)
                        $rental->amount = round($totalCents / 100, 2);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
            // Non blocchiamo la creazione bozza: amount resta quello precedente (o 0 di default)
        }

        if($rental->status === 'draft' || $rental->status === null) {
            $rental->status = 'draft';
        }
        $rental->save();
        $this->rentalId = $rental->id;
        
        // === Coverage 1:1 (creazione idempotente) ===============================
        // Garantisce l'esistenza della riga su rental_coverages per questo rental.
        // Usiamo firstOrCreate per rispettare la unique(rental_id) e non creare duplicati.
        // Default: rca=true, altri false, franchigie/null (verranno aggiornate in step successivi).
        $rental->coverage()->firstOrCreate(
            ['rental_id' => $rental->id],
            [
                'rca'                        => true,   // obbligatoria nel tuo flusso
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
        // ========================================================================

        // Feedback UI
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

    /** Selezione cliente: popola il form invece del toast e collega alla bozza */
    public function selectCustomer(int $id): void
    {
        $customer = Customer::query()->findOrFail($id);

        $this->customer_id = $customer->id;

        // Popola il form con i dati trovati (campi compatibili con la tua tabella customers)
        $this->customerForm = [
            'name'          => $customer->name,
            'email'         => $customer->email,
            'phone'         => $customer->phone,
            'doc_id_type'   => $customer->doc_id_type,
            'doc_id_number' => $customer->doc_id_number,
            'birth_date'    => optional($customer->birthdate)->format('Y-m-d'),
            'address'       => $customer->address_line,
            'city'          => $customer->city,
            'province'      => $customer->province,
            'zip'           => $customer->postal_code,
            'country_code'  => $customer->country_code,
            'driver_license_number'      => $customer->driver_license_number,
            'driver_license_expires_at'  => optional($customer->driver_license_expires_at)->format('Y-m-d'),
        ];
        $this->customerPopulated = true;

        // Collega alla bozza in modo silenzioso
        $this->saveDraft();
    }

    /** Crea o aggiorna il cliente usando i dati nel form e collega alla bozza */
    public function createOrUpdateCustomer(): void
    {
        $this->validate($this->rulesCustomerCreate());

        if ($this->customer_id) {
            $customer = Customer::query()->findOrFail($this->customer_id);
            $customer->fill($this->customerForm)->save();
        } else {
            $customer = new Customer($this->customerForm);
            $customer->save();
            $this->customer_id = $customer->id;
        }

        $this->customerPopulated = true;
        $this->saveDraft();
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
     */
    protected function assertVehicleAvailability(): void
    {
        $vehicleId = $this->rentalData['vehicle_id'] ?? null;
        $start     = $this->castDate($this->rentalData['planned_pickup_at'] ?? null);
        $end       = $this->castDate($this->rentalData['planned_return_at'] ?? null);

        if (!$vehicleId || !$start || !$end) return; // mancano dati => non blocchiamo qui

        $exists = Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->when($this->rentalId, fn(Builder $q) => $q->where('id','!=',$this->rentalId))
            // qualsiasi stato (compresi draft/reserved/checked_*)
            // se vuoi escludere cancelled/no_show, filtra qui.
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
     */
    protected function assertCustomerNoOverlap(): void
    {
        $customerId = $this->customer_id;
        $start      = $this->castDate($this->rentalData['planned_pickup_at'] ?? null);
        $end        = $this->castDate($this->rentalData['planned_return_at'] ?? null);

        if (!$customerId || !$start || !$end) return;

        $exists = Rental::query()
            ->where('customer_id', $customerId)
            ->when($this->rentalId, fn(Builder $q) => $q->where('id','!=',$this->rentalId))
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

        // Scadenza patente (solo data) dal form cliente
        $expires = $this->castDate($this->customerForm['driver_license_expires_at'] ?? null);

        // Se mancano i dati necessari, non blocchiamo qui
        if (!$end || !$expires) {
            return;
        }

        // Consideriamo valida la patente fino alle 23:59:59 del giorno di scadenza
        $expiresEndOfDay = $expires->copy()->endOfDay();

        // Se la riconsegna è successiva al termine della giornata di scadenza → blocco
        if ($end->greaterThan($expiresEndOfDay)) {
            // Feedback UI immediato
            $this->dispatch('toast', type: 'error', message: 'La patente del cliente risulta scadere prima della fine del noleggio.');

            // Errori di validazione associati sia alla patente sia alla data di riconsegna
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
