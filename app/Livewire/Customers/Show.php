<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Show extends Component
{
    use AuthorizesRequests;

    /** @var Customer */
    public Customer $customer;

    // --- Campi bindati (identitari + contatti + indirizzo base tab "Dati") ---
    public ?string $name = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $doc_id_type = null;
    public ?string $doc_id_number = null;
    public ?string $birthdate = null;       // Y-m-d
    public ?string $address_line = null;
    public ?string $city = null;
    public ?string $province = null;
    public ?string $postal_code = null;
    public ?string $country_code = null;
    public ?string $notes = null;

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer;

        // Precarico i valori in form
        $this->fill($customer->only([
            'name','email','phone','doc_id_type','doc_id_number','birthdate',
            'address_line','city','province','postal_code','country_code','notes',
        ]));
        $this->birthdate = optional($customer->birthdate)->format('Y-m-d');
    }

    /**
     * Regole di validazione: rispettano i vincoli DB (migrations) e la uniqueness per tenant.
     */
    protected function rules(): array
    {
        $orgId = (int) $this->customer->organization_id;
        $id    = (int) $this->customer->id;

        return [
            'name'          => ['required','string','min:2','max:191'],
            'email'         => ['nullable','email:rfc,dns','max:191'],
            'phone'         => ['nullable','string','max:32'],
            'doc_id_type'   => ['nullable', Rule::in(['id','passport','license','other'])],
            'doc_id_number' => [
                'nullable','string','min:3','max:64',
                // unique per tenant, ignorando il record corrente
                Rule::unique('customers','doc_id_number')
                    ->ignore($id)
                    ->where(fn($q) => $q->where('organization_id', $orgId)),
            ],
            'birthdate'     => ['nullable','date','after:1900-01-01','before_or_equal:today'],
            'address_line'  => ['nullable','string','max:191'],
            'city'          => ['nullable','string','max:128'],
            'province'      => ['nullable','string','max:64'],
            'postal_code'   => ['nullable','string','max:16'],
            'country_code'  => ['nullable','string','size:2'],
            'notes'         => ['nullable','string'],
        ];
    }

    public function save(): void
    {
        // Autorizzazione a modificare (renter: permesso customers.update, scoping su organization)
        $this->authorize('update', $this->customer);

        $validated = $this->validate();

        // Update atomico
        $this->customer->fill($validated)->save();

        $this->dispatch('toast', type: 'success', message: 'Dati cliente aggiornati');
    }

    public function render()
    {
        return view('livewire.customers.show');
    }
}
