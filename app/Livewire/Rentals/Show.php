<?php

namespace App\Livewire\Rentals;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Rental;
use App\Models\Customer;
use App\Services\Contracts\GenerateRentalContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Livewire: Scheda noleggio con tab e Action Drawer.
 *
 * - Non modifica nomi/relazioni esistenti.
 * - Le transizioni di stato invocano rotte POST di RentalController (submit form hidden).
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** @var Rental Istanza di noleggio */
    public Rental $rental;

    /** @var string Tab attivo: data|contract|pickup|return|damages|attachments|timeline */
    public string $tab = 'data';

    protected $queryString = [
        'tab' => ['as' => 'tab', 'except' => 'data']
    ];

    /**
     * Modale gestione Cliente (Aggiungi / Cambia)
     * - Usiamo gli stessi campi del form già in uso nel wizard.
     */
    public bool $customerModalOpen = false;

    /** @var bool Se true, stiamo modificando un cliente esistente (prefill). */
    public bool $customerPopulated = false;

    /** @var int|null Id cliente in modifica (se customerPopulated = true). */
    public ?int $customer_id = null;

    /** @var array Form cliente (chiavi identiche a quelle già usate nel progetto). */
    public array $customerForm = [
        'name'                     => null,
        'email'                    => null,
        'phone'                    => null,
        'doc_id_type'              => null,
        'doc_id_number'            => null,
        'birth_date'               => null,
        'address'                  => null,
        'city'                     => null,
        'province'                 => null,
        'zip'                      => null,
        'country_code'             => null,
        'driver_license_number'    => null,
        'driver_license_expires_at'=> null,
    ];

    /**
     * Ricerca cliente (aperta):
     * - Usata per cercare cliente esistente e poi selezionarlo.
     * - Nessun filtro per organization/renter: evita doppioni se un cliente noleggia con renter diversi.
     */
    public string $customerQuery = '';

    public function mount(Rental $rental): void
    {
        $this->rental = $rental->load(['customer','vehicle','checklists','damages']);
    }

    public function switch(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * Regole di validazione del form cliente.
     * - Allineate ai campi obbligatori mostrati nel form (nome + documento).
     */
    protected function customerRules(): array
    {
        return [
            'customerForm.name'          => ['required', 'string', 'max:255'],
            'customerForm.email'         => ['nullable', 'email', 'max:255'],
            'customerForm.phone'         => ['nullable', 'string', 'max:32'],

            'customerForm.doc_id_type'   => ['required', 'in:id,passport'],
            'customerForm.doc_id_number' => ['required', 'string', 'max:64'],

            'customerForm.driver_license_number'     => ['nullable', 'string', 'max:64'],
            'customerForm.driver_license_expires_at' => ['nullable', 'date'],

            'customerForm.birth_date'    => ['nullable', 'date'],
            'customerForm.address'       => ['nullable', 'string', 'max:255'],
            'customerForm.city'          => ['nullable', 'string', 'max:128'],
            'customerForm.province'      => ['nullable', 'string', 'max:16'],
            'customerForm.zip'           => ['nullable', 'string', 'max:16'],
            'customerForm.country_code'  => ['nullable', 'string', 'size:2'],
        ];
    }

    /**
     * Reset del form cliente.
     */
    protected function resetCustomerForm(): void
    {
        $this->customerPopulated = false;
        $this->customer_id = null;

        foreach ($this->customerForm as $key => $value) {
            $this->customerForm[$key] = null;
        }
    }

    /**
     * Apre il modale Cliente.
     *
     * @param bool $prefillCurrent
     *  - false: form vuoto (use-case "Aggiungi Cliente" o "Cambia Cliente" creando un nuovo record)
     *  - true : precompila dal cliente corrente per aggiornamento (use-case futuro)
     */
    public function openCustomerModal(bool $prefillCurrent = false): void
    {
        // 🔐 Autorizzazione: stai modificando il Rental (associazione cliente)
        $this->authorize('update', $this->rental);

        // ✅ Regola di business: cliente modificabile solo in draft/reserved
        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile cambiare cliente dopo l’avvio del noleggio.');
            return;
        }

        $this->resetErrorBag();
        $this->resetValidation();

        if ($prefillCurrent && $this->rental->customer) {
            $c = $this->rental->customer;

            $this->customerPopulated = true;
            $this->customer_id = (int) $c->id;

            // Pre-fill mantenendo i nomi delle chiavi invariati
            $this->customerForm['name'] = $c->name;
            $this->customerForm['email'] = $c->email;
            $this->customerForm['phone'] = $c->phone;

            $this->customerForm['doc_id_type'] = $c->doc_id_type;
            $this->customerForm['doc_id_number'] = $c->doc_id_number;

            $this->customerForm['driver_license_number'] = $c->driver_license_number;
            $this->customerForm['driver_license_expires_at'] = $c->driver_license_expires_at
                ? optional($c->driver_license_expires_at)->format('Y-m-d')
                : null;

            $this->customerForm['birth_date'] = $c->birth_date
                ? optional($c->birth_date)->format('Y-m-d')
                : null;

            $this->customerForm['address'] = $c->address;
            $this->customerForm['city'] = $c->city;
            $this->customerForm['province'] = $c->province;
            $this->customerForm['zip'] = $c->zip;
            $this->customerForm['country_code'] = $c->country_code;
        } else {
            // Caso normale: vogliamo creare un nuovo cliente e associarlo
            $this->resetCustomerForm();
        }

        $this->customerModalOpen = true;
    }

    /**
     * Chiude il modale Cliente.
     */
    public function closeCustomerModal(): void
    {
        $this->customerModalOpen = false;
    }

    /**
     * Crea o aggiorna il cliente e lo associa al noleggio.
     * - Hookato dal form: wire:click="createOrUpdateCustomer"
     */
    public function createOrUpdateCustomer(): void
    {
        // 🔐 Autorizzazione su Rental
        $this->authorize('update', $this->rental);

        // ✅ Regola di business: cliente modificabile solo in draft/reserved
        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile cambiare cliente dopo l’avvio del noleggio.');
            return;
        }

        $this->validate($this->customerRules());

        // Normalizzazioni leggere (non invasive)
        if (!empty($this->customerForm['country_code'])) {
            $this->customerForm['country_code'] = strtoupper(trim((string) $this->customerForm['country_code']));
        }

        try {
            DB::transaction(function () {
                // Se stiamo modificando un cliente esistente (prefill)
                if ($this->customerPopulated === true && $this->customer_id) {
                    $customer = Customer::query()->findOrFail($this->customer_id);
                    $customer->fill($this->customerForm);
                    $customer->save();
                } else {
                    // Caso standard per il tuo flusso "Cambia Cliente": crea nuovo record e associa
                    $customer = new Customer();
                    $customer->fill($this->customerForm);
                    $customer->save();
                }

                // Associa il cliente al rental
                $this->rental->customer_id = (int) $customer->id;
                $this->rental->save();
            });

            // 🔄 Refresh per riflettere subito il cambio in UI
            $this->rental->refresh();
            $this->rental->load(['customer']);

            $this->customerModalOpen = false;

            $this->dispatch('toast', type: 'success', message: 'Cliente associato al noleggio.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante il salvataggio del cliente.');
        }
    }

    /**
     * Risultati ricerca clienti (computed).
     * - Ricerca aperta: nome o numero documento, min 2 caratteri, max 10 risultati.
     *
     * @return array<int, array{id:int,name:string,doc_id_number:?string}>
     */
    public function getCustomerSearchResultsProperty(): array
    {
        // 🔐 Sicurezza: stai per associare un cliente a un rental, quindi richiediamo update sul rental
        $this->authorize('update', $this->rental);

        if (mb_strlen($this->customerQuery) < 2) {
            return [];
        }

        $q = trim($this->customerQuery);

        return Customer::query()
            // ✅ Raggruppamento corretto della OR
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                      ->orWhere('doc_id_number', 'like', '%' . $q . '%');
            })
            ->limit(10)
            ->get(['id', 'name', 'doc_id_number'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => (string) $c->name,
                'doc_id_number' => $c->doc_id_number,
            ])
            ->toArray();
    }

    /**
     * Selezione cliente esistente:
     * - Compila il form con i dati del cliente (no re-typing).
     * - Imposta customer_id e customerPopulated.
     *
     * NB: Qui NON salvo/associo automaticamente al rental:
     *     l'associazione avviene quando confermi con createOrUpdateCustomer().
     */
    public function selectCustomer(int $id): void
    {
        $this->authorize('update', $this->rental);

        // ✅ Ricerca/associazione aperta: nessun filtro per renter/organization
        $customer = Customer::query()->findOrFail($id);

        $this->customer_id = (int) $customer->id;

        // Popola il form con i dati trovati (replica del CreateWizard)
        $this->customerForm = [
            'name'          => $customer->name,
            'email'         => $customer->email,
            'phone'         => $customer->phone,
            'doc_id_type'   => $customer->doc_id_type,
            'doc_id_number' => $customer->doc_id_number,

            // Nel tuo wizard usi "birthdate" (non "birth_date")
            'birth_date'    => optional($customer->birthdate)->format('Y-m-d'),

            // Nel tuo wizard usi "address_line" e "postal_code"
            'address'       => $customer->address_line,
            'city'          => $customer->city,
            'province'      => $customer->province,
            'zip'           => $customer->postal_code,
            'country_code'  => $customer->country_code,

            'driver_license_number'      => $customer->driver_license_number,
            'driver_license_expires_at'  => optional($customer->driver_license_expires_at)->format('Y-m-d'),
        ];

        $this->customerPopulated = true;

        // Quando selezioni un cliente, pulisci la query per “chiudere” la lista risultati (UI)
        $this->customerQuery = '';
    }

    /**
     * Genera una nuova versione del contratto (PDF) e la salva su Media Library (collection "contract").
     * - Autorizza l'utente (policy/permessi).
     * - Usa il service GenerateRentalContract (già fornito).
     * - Mostra un toast e aggiorna il componente Livewire senza navigare.
     */
    public function generateContract(GenerateRentalContract $generator): void
    {
        // 🔐 Autorizzazione
        if (!auth()->user()?->can('rentals.contract.generate') || !auth()->user()?->can('media.attach.contract')) {
            $this->dispatch('toast', type: 'error', message: 'Permesso negato.');
            return;
        }

        // ✅ Regola business: niente contratto senza cliente
        if (empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'error', message: 'Associa prima un cliente al noleggio.');
            return;
        }

        // ✅ Coerenza flusso: contratto generabile prima dell’avvio (draft/reserved)
        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non puoi generare il contratto in questa fase del noleggio.');
            return;
        }

        try {
            $generator->handle($this->rental);

            // ✅ Dopo generazione contratto: se era draft, diventa reserved
            if ($this->rental->status === 'draft') {
                $this->rental->status = 'reserved';
                $this->rental->save();
            }

            $this->rental->refresh();
            $this->dispatch('toast', type: 'success', message: 'Contratto generato.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la generazione del contratto.');
        }
    }


    /** ✅ Arriva da JS quando viene caricata la checklist firmata */
    #[On('checklist-signed-uploaded')]
    public function onChecklistSignedUploaded(array $payload = []): void
    {
        // Se vuoi, filtra per rentalId (utile con più istanze aperte)
        if (isset($payload['rentalId']) && (int)$payload['rentalId'] !== (int)$this->rental->id) {
            return;
        }

        $this->rental->refresh();
        $this->rental->load(['checklists.media']);

        // se stai visualizzando già la checklist, rimani lì; altrimenti non cambio tab
        $this->dispatch('$refresh');
    }

    /** ✅ Arriva da JS quando si carica/elimina una foto */
    #[On('checklist-media-updated')]
    public function onChecklistMediaUpdated(): void
    {
        $this->rental->refresh();
        $this->rental->load(['checklists.media']);
        $this->dispatch('$refresh');
    }

    public function render(): View
    {
        return view('livewire.rentals.show');
    }
}
