<?php

namespace App\Livewire\Rentals;

use Livewire\Component;
use App\Models\{Rental, Customer, Vehicle, Location, VehicleAssignment};
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

/**
 * Wizard creazione noleggio in stato draft:
 * 1) Dati noleggio
 * 2) Cliente
 * 3) Bozza (contratto + documenti preliminari)
 *
 * Nota: non rinomina campi; salva/aggiorna sempre con status='draft'.
 */
class CreateWizard extends Component
{
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

    /** Creazione rapida cliente (se non esiste) — usa i campi minimi previsti da customers */
    public array $customerForm = [
        'full_name'     => null,
        'email'         => null,
        'phone'         => null,
        'doc_id_type'   => null,
        'doc_id_number' => null,
    ];

    /** Ricerca cliente */
    public string $customerQuery = '';

    /** Opzioni di select (caricate da DB / repository) */
    public $vehicles = [];
    public $locations = [];

    public function mount(): void
    {
        // Carica opzioni per select (minimal, puoi sostituire con repository/service)
        $this->vehicles  = Vehicle::query()->orderBy('id','desc')->limit(100)->get(['id','plate','make', 'model'])->toArray();
        // ipotizziamo un modello Location collegato; in assenza, lascia array vuoto
        $this->locations = Location::query()->orderBy('name')->get(['id','name'])->toArray();
    }

    /** Regole di validazione base per step 1 */
    protected function rulesStep1(): array
    {
        return [
            'rentalData.vehicle_id'         => ['nullable','integer','exists:vehicles,id'],
            'rentalData.pickup_location_id' => ['nullable','integer'],
            'rentalData.return_location_id' => ['nullable','integer'],
            'rentalData.planned_pickup_at'  => ['nullable','date'],
            'rentalData.planned_return_at'  => ['nullable','date','after_or_equal:rentalData.planned_pickup_at'],
            'rentalData.notes'              => ['nullable','string'],
        ];
    }

    /** Regole di validazione per creazione rapida customer (step 2) */
    protected function rulesCustomerCreate(): array
    {
        return [
            'customerForm.name'     => ['required','string','max:255'],
            'customerForm.email'         => ['nullable','email','max:255'],
            'customerForm.phone'         => ['nullable','string','max:50'],
            'customerForm.doc_id_type'   => ['nullable','string','max:50'],
            'customerForm.doc_id_number' => ['required','string','max:100'],
        ];
    }

    /** Salva/aggiorna la bozza (sempre status=draft) */
    public function saveDraft(): void
    {
        $this->validate($this->rulesStep1());

        // Crea o aggiorna la bozza
        $rental = $this->rentalId
            ? Rental::query()->findOrFail($this->rentalId)
            : new Rental();

        // Imposta status 'draft' (fase neutra)
        $rental->status = 'draft';

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
        $rental->organization_id    = auth()->user()->organization_id; // imposta se necessario
        $rental->assignment_id      = $assignment->id ?? null; // resetta se necessario
        $rental->notes              = $this->rentalData['notes'] ?? null;

        // customer_id se già selezionato nello step 2
        if ($this->customer_id) {
            $rental->customer_id = $this->customer_id;
        }

        // Imposta organization/created_by se nel tuo progetto sono obbligatori (omessi qui per non rinominare variabili)
        $rental->save();

        $this->rentalId = $rental->id;

        // Feedback UI
        $this->dispatch('toast', type:'success', message:'Bozza salvata.');
    }

    /** Step → avanti (salva prima la bozza così abbiamo l'ID per gli upload) */
    public function next(): void
    {
        if ($this->step === 1) {
            $this->saveDraft();
        }
        if ($this->step < 3) {
            $this->step++;
        }
    }

    /** Step ← indietro */
    public function prev(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    /** Associa un cliente trovato (step 2) e salva bozza */
    public function selectCustomer(int $id): void
    {
        $this->customer_id = $id;
        $this->saveDraft();
    }

    /** Crea al volo il cliente (step 2) e associa alla bozza */
    public function createCustomer(): void
    {
        $this->validate($this->rulesCustomerCreate());

        // Trova duplicati per (organization_id, doc_id_number) se necessario
        $customer = new Customer($this->customerForm);
        // Imposta organization se obbligatorio nel tuo schema (omesso qui per compatibilità)
        $customer->save();

        $this->customer_id = $customer->id;
        $this->saveDraft();

        $this->dispatch('toast', type:'success', message:'Cliente creato e associato.');
    }

    /** Conferma bozza: redirect allo show o alla pagina reserved (qui solo redirect allo show) */
    public function finish(): void
    {
        // Non settiamo reserved qui: l’azione formale di passaggio di stato resta nei controller di transizione.
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
