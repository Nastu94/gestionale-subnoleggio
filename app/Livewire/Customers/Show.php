<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Livewire: Clienti ▸ Dettaglio
 *
 * - Il renter può modificare TUTTI i campi identitari + contatti + residenza.
 * - Unicità doc_id_number per tenant (organization_id).
 * - Notifiche via toast (evento Livewire) invece di flash/redirect.
 * - Aggiunta gestione patente (numero + scadenza).
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** Cliente corrente (route-model binding) */
    public Customer $customer;

    // Bind form (campi esistenti)
    public ?string $name = null;
    public ?string $doc_id_type = null;         // select UI: id|passport (altri valori preservati in sola lettura)
    public ?string $doc_id_number = null;
    public ?string $birthdate = null;           // Y-m-d
    public ?string $email = null;
    public ?string $phone = null;

    // Patente (nuovi campi)
    public ?string $driver_license_number = null;
    public ?string $driver_license_expires_at = null; // Y-m-d

    // Residenza (unico indirizzo)
    public ?string $address_line = null;
    public ?string $city = null;
    public ?string $province = null;
    public ?string $postal_code = null;
    public ?string $country_code = null;

    public ?string $notes = null;

    public function mount(Customer $customer): void
    {
        // Autorizzazioni base
        $this->authorize('view', $customer);
        $this->customer = $customer;

        // Precarico valori dal model (nessun rename)
        $this->fill($customer->only([
            'name','doc_id_type','doc_id_number','email','phone',
            'driver_license_number','address_line','city','province','postal_code','country_code','notes',
        ]));

        // Normalizza date per input[type=date]
        $this->birthdate = optional($customer->birthdate)->format('Y-m-d');
        $this->driver_license_expires_at = optional($customer->driver_license_expires_at)->format('Y-m-d');
    }

    /** Regole di validazione coerenti con i vincoli DB */
    protected function rules(): array
    {
        $orgId = (int) $this->customer->organization_id;
        $id    = (int) $this->customer->id;

        return [
            'name'          => ['required','string','min:2','max:191'],

            // Nota: a DB il tipo è string/enum; in UI limitiamo le scelte, ma accettiamo valori esistenti.
            'doc_id_type'   => ['nullable','string','max:32'],

            'doc_id_number' => [
                'nullable','string','min:3','max:64',
                Rule::unique('customers','doc_id_number')
                    ->ignore($id)
                    ->where(fn($q) => $q->where('organization_id', $orgId)),
            ],

            'birthdate'     => ['nullable','date','after:1900-01-01','before_or_equal:today'],
            'email'         => ['nullable','email','max:191'],
            'phone'         => ['nullable','string','max:32'],

            // Patente (nuovi)
            'driver_license_number'      => ['nullable','string','max:64'],
            'driver_license_expires_at'  => ['nullable','date','after:1900-01-01'],

            // Residenza (unico indirizzo)
            'address_line'  => ['nullable','string','max:191'],
            'city'          => ['nullable','string','max:128'],
            'province'      => ['nullable','string','max:64'],
            'postal_code'   => ['nullable','string','max:16'],
            'country_code'  => ['nullable','string','size:2'],

            'notes'         => ['nullable','string'],
        ];
    }

    /**
     * Salva modifiche e mostra TOAST (niente redirect/flash).
     * - Policy: CustomerPolicy@update (renter ammesso).
     * - Dopo il save: refresh del model + refill del form per coerenza UI.
     */
    public function save(): void
    {
        $this->authorize('update', $this->customer);

        $data = $this->validate();

        // Assign & save (se il Model ha cast sulle date, la stringa Y-m-d è ok)
        $this->customer->fill($data)->save();

        // Ricarico e riallineo il form (utile se ci sono mutator/cast dal DB)
        $this->customer->refresh();
        $this->fill($this->customer->only([
            'name','doc_id_type','doc_id_number','email','phone',
            'driver_license_number','address_line','city','province','postal_code','country_code','notes',
        ]));
        $this->birthdate = optional($this->customer->birthdate)->format('Y-m-d');
        $this->driver_license_expires_at = optional($this->customer->driver_license_expires_at)->format('Y-m-d');

        // Toast di successo (listener globale già presente nel progetto)
        $this->dispatch('toast', type: 'success', message: 'Dati cliente aggiornati correttamente.');
    }

    public function render()
    {
        // Opzioni UI per Tipo documento d'identità (limitate a id/passport)
        $docIdOptions = [
            'id'       => "Carta d'identità",
            'passport' => 'Passaporto',
        ];

        return view('livewire.customers.show', [
            'docIdOptions' => $docIdOptions,
        ]);
    }
}
