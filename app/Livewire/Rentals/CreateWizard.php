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
        'kasko'           => false,
        'furto_incendio'  => false,
        'cristalli'       => false,
        'assistenza'      => false,
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

    public function mount(): void
    {
        $this->loadOptions();
        $this->refreshCurrentContract();               // se $rentalId è già presente
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
        $orgId  = method_exists($user, 'organization') ? optional($user->organization)->id : ($user->organization_id ?? null);
        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;

        $vehiclesQ = Vehicle::query();

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
            // Veicoli senza assegnazione aperta
            $vehiclesQ->whereDoesntHave('assignments', function ($q) {
                $q->whereNull('end_at'); // o 'returned_at' a seconda del tuo schema
            });
        } else {
            if ($orgId) {
                $vehiclesQ->whereHas('assignments', function ($q) use ($orgId) {
                    $q->whereNull('end_at')      // assegnazione attiva
                      ->where('organization_id', $orgId);
                });
            } else {
                // fallback conservativo: nessun veicolo
                $vehiclesQ->whereRaw('1=0');
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
            'rentalData.vehicle_id'         => ['nullable','integer','exists:vehicles,id'],
            'rentalData.pickup_location_id' => ['nullable','integer','exists:locations,id'],
            'rentalData.return_location_id' => ['nullable','integer','exists:locations,id'],
            'rentalData.planned_pickup_at'  => ['nullable','date'],
            'rentalData.planned_return_at'  => ['nullable','date','after_or_equal:rentalData.planned_pickup_at'],
            'rentalData.notes'              => ['nullable','string'],
            'coverage.kasko'           => ['boolean'],
            'coverage.furto_incendio'  => ['boolean'],
            'coverage.cristalli'       => ['boolean'],
            'coverage.assistenza'      => ['boolean'],

            // Se una copertura è selezionata, la relativa franchigia può essere richiesta (qui la lasciamo facoltativa).
            'franchise.kasko'          => ['nullable','numeric','min:0'],
            'franchise.furto_incendio' => ['nullable','numeric','min:0'],
            'franchise.cristalli'      => ['nullable','numeric','min:0'],
        ];
    }

    /** Regole per creazione/aggiornamento cliente */
    protected function rulesCustomerCreate(): array
    {
        return [
            'customerForm.name'          => ['required','string','max:255'],
            'customerForm.email'         => ['nullable','email','max:255'],
            'customerForm.phone'         => ['nullable','string','max:50'],
            'customerForm.doc_id_type'   => ['required','in:id,passport'],
            'customerForm.doc_id_number' => ['required','string','max:100'],
            'customerForm.birth_date'    => ['nullable','date'],
            'customerForm.address'       => ['nullable','string','max:255'],
            'customerForm.city'          => ['nullable','string','max:100'],
            'customerForm.province'      => ['nullable','string','max:10'],
            'customerForm.zip'           => ['nullable','string','max:20'],
            'customerForm.country_code'  => ['nullable','string','max:2'],
            'customerForm.driver_license_number'      => ['nullable','string','max:64'],
            'customerForm.driver_license_expires_at'  => ['nullable','date'],
        ];
    }

    /** Salva/aggiorna la bozza (sempre status=draft) */
    public function saveDraft(): void
    {
        $this->validate($this->rulesStep1());

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
        $rental->status = 'draft';
        $rental->save();
        $this->rentalId = $rental->id;

        // Feedback UI
        $this->dispatch('toast', type:'success', message:'Bozza salvata.');
    }

    /** Avanti di step (salviamo in 1 → 2 per avere l’ID bozza) */
    public function next(): void
    {
        if ($this->step === 1) {
            // validazioni base + salvataggio bozza
            $this->saveDraft();
            // check overlap veicolo
            $this->assertVehicleAvailability();
        }

        if ($this->step === 2) {
            // se ha selezionato o creato un cliente, controlla overlap sul cliente
            if ($this->customer_id) {
                $this->assertCustomerNoOverlap();
            }
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
            return;
        }

        $vehicle = Vehicle::query()->find($vehicleId);
        if (!$vehicle) return;

        $currentLocationId =
            $vehicle->default_pickup_location_id
            ?? $vehicle->current_location_id
            ?? optional($vehicle->location)->id
            ?? $vehicle->pickup_location_id
            ?? null;

        if ($currentLocationId) {
            $this->rentalData['pickup_location_id'] = $currentLocationId;
        }
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
                ->orWhere('doc_id_number','like','%'.$this->customerQuery.'%')
                ->limit(10)
                ->get(['id','name','doc_id_number'])
                ->toArray();
        }

        return view('livewire.rentals.create-wizard', [
            'customers' => $customers,
        ]);
    }
}
