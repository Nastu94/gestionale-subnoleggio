<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\CargosLuogo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Livewire: Clienti ▸ Dettaglio
 *
 * REFRACTOR:
 * - I luoghi CARGOS (residenza/nascita) sono gestiti dal componente riutilizzabile:
 *   <livewire:shared.cargos-luogo-picker wire:model="..."/>
 * - Questo componente padre conserva solo i "place_code" finali:
 *   - police_place_code  (residenza)  -> richiesto
 *   - birth_place_code   (nascita)    -> opzionale (ma spesso richiesto da CARGOS)
 *
 * NOTE:
 * - In tabella CargosLuogo, per le NAZIONI "country_code" può non essere valorizzato.
 * - La logica ITALIA/ESTERO e i dropdown sono nel picker, non qui.
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** Cliente corrente (route-model binding) */
    public Customer $customer;

    // =========================
    // Campi base customer
    // =========================
    public ?string $name = null;
    
    /** Nome per CARGOS */
    public ?string $first_name = null;

    /** Cognome per CARGOS */
    public ?string $last_name = null;

    public ?string $doc_id_type = null;     // select UI: id|passport (altri valori preservati)
    public ?string $doc_id_number = null;

    /** Codice Fiscale (IT) */
    public ?string $tax_code = null;

    /** Partita IVA (IT) */
    public ?string $vat = null;

    public ?string $birthdate = null;       // Y-m-d

    public ?string $email = null;
    public ?string $phone = null;

    // Patente
    public ?string $driver_license_number = null;
    public ?string $driver_license_expires_at = null; // Y-m-d

    // Residenza testuale (non CARGOS)
    public ?string $address_line = null;
    public ?string $city = null;
    public ?string $province = null;
    public ?string $postal_code = null;
    public ?string $country_code = null;

    public ?string $notes = null;

    // =========================
    // CARGOS "place_code" finali (collegati ai picker)
    // =========================

    /** Codice CARGOS residenza (customer.police_place_code) */
    public ?int $police_place_code = null;

    /** Codice CARGOS luogo di nascita (customer.birth_place_code) */
    public ?int $birth_place_code = null;

    // =========================
    // CARGOS - Tipi documento
    // =========================

    /** Codice CARGOS documento identità */
    public ?string $identity_document_type_code = null;

    /** Codice CARGOS documento patente (sempre PATEN) */
    public ?string $driver_license_document_type_code = null;

    // =========================
    // CARGOS - Luoghi rilascio documenti
    // =========================

    /** Codice CARGOS luogo rilascio documento identità */
    public ?int $identity_document_place_code = null;

    /** Codice CARGOS luogo rilascio patente */
    public ?int $driver_license_place_code = null;

    //  =========================
    // CARGOS - Cittadinanza
    //  =========================

    /** Codice CARGOS cittadinanza (solo nazione) */
    public ?int $citizenship_place_code = null;

    // =========================
    // Lifecycle
    // =========================
    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer;

        $this->fill($customer->only([
            'name',
            'doc_id_type',
            'doc_id_number',
            'email',
            'phone',
            'driver_license_number',
            'address_line',
            'city',
            'province',
            'postal_code',
            'country_code',
            'notes',
            'tax_code',
            'vat',
            'birthdate',
            'driver_license_expires_at',
        ]));

        $this->first_name = $customer->first_name;
        $this->last_name  = $customer->last_name;

        if (! $this->first_name && ! $this->last_name && $customer->name) {
            [$fn, $ln] = array_pad(explode(' ', $customer->name, 2), 2, null);
            $this->first_name = $fn;
            $this->last_name  = $ln;
        }

        // CARGOS place codes
        $this->police_place_code = $customer->police_place_code;
        $this->birth_place_code  = $customer->birth_place_code;

        // ✅ FIX QUI
        $this->citizenship_place_code = $customer->citizenship_cargos_code;

        $this->birthdate = optional($customer->birthdate)->format('Y-m-d');
        $this->driver_license_expires_at = optional($customer->driver_license_expires_at)->format('Y-m-d');

        $this->identity_document_type_code = $customer->identity_document_type_code;
        $this->driver_license_document_type_code = $customer->driver_license_document_type_code ?? 'PATEN';

        $this->identity_document_place_code = $customer->identity_document_place_code;
        $this->driver_license_place_code    = $customer->driver_license_place_code;
    }

    /**
     * Mappa i codici CARGOS dei tipi documento ai valori interni usati nell'applicazione.
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

    // =========================
    // Validation
    // =========================
    protected function rules(): array
    {
        $orgId = (int) $this->customer->organization_id;
        $id    = (int) $this->customer->id;

        return [
            'name'          => ['required', 'string', 'min:2', 'max:191'],
            'doc_id_type'   => ['nullable', 'string', 'max:32'],

            'doc_id_number' => [
                'nullable', 'string', 'min:3', 'max:64',
                Rule::unique('customers', 'doc_id_number')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('organization_id', $orgId)),
            ],

            'birthdate'     => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'email'         => ['nullable', 'email', 'max:191'],
            'phone'         => ['nullable', 'string', 'max:32'],

            'driver_license_number'     => ['nullable', 'string', 'max:64'],
            'driver_license_expires_at' => ['nullable', 'date', 'after:1900-01-01'],

            'tax_code' => ['nullable', 'string', 'max:32'],
            'vat'      => ['nullable', 'string', 'max:32'],

            'address_line' => ['nullable', 'string', 'max:191'],
            'city'         => ['nullable', 'string', 'max:128'],
            'province'     => ['nullable', 'string', 'max:64'],
            'postal_code'  => ['nullable', 'string', 'max:16'],
            'country_code' => ['nullable', 'string', 'size:2'],

            'notes' => ['nullable', 'string'],

            // CARGOS place codes (collegati ai picker)
            // - police_place_code: deve essere sempre valorizzato (come richiesto)
            'police_place_code' => ['required', 'integer', 'exists:cargos_luoghi,code'],

            // - birth_place_code: lo lasciamo nullable (ma esiste se valorizzato)
            'birth_place_code'  => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
            
            // =========================
            // CARGOS - Tipi documento
            // =========================

            'identity_document_type_code' => [
                'nullable',
                'string',
                'exists:cargos_document_types,code',
            ],

            // La patente NON è input utente, ma validiamo comunque lo stato
            'driver_license_document_type_code' => [
                'required',
                'string',
                Rule::in(['PATEN']),
            ],

            // =========================
            // CARGOS - Luoghi rilascio documenti
            // =========================
            'identity_document_place_code' => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
            'driver_license_place_code'    => ['nullable', 'integer', 'exists:cargos_luoghi,code'],

            // Cittadinanza CARGOS (solo nazione)
            'citizenship_place_code' => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
            'first_name' => ['nullable', 'string', 'max:191'],
            'last_name'  => ['nullable', 'string', 'max:191'],
        ];
    }
    
    // =========================
    // Actions
    // =========================
    public function save(): void
    {
        $this->authorize('update', $this->customer);

        $data = $this->validate();

        // ❗ campo virtuale UI
        unset($data['citizenship_place_code']);

        // === CARGOS ===
        $this->customer->police_place_code = $this->police_place_code;
        $this->customer->birth_place_code  = $this->birth_place_code;

        if ($this->birth_place_code) {
            $luogo = CargosLuogo::find($this->birth_place_code);
            $this->customer->birth_place = $luogo?->name;
        } else {
            $this->customer->birth_place = null;
        }

        // Documenti
        $this->customer->identity_document_type_code = $this->identity_document_type_code;
        $this->customer->doc_id_type =
            $this->mapCargosDocTypeToInternal($this->identity_document_type_code);

        $this->customer->driver_license_document_type_code = 'PATEN';
        $this->customer->identity_document_place_code = $this->identity_document_place_code;
        $this->customer->driver_license_place_code    = $this->driver_license_place_code;

        // ✅ FIX DEFINITIVO
        $this->customer->citizenship_cargos_code = $this->citizenship_place_code;

        $this->customer->first_name = $this->first_name;
        $this->customer->last_name  = $this->last_name;
        $data['name'] = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        $this->customer->fill($data)->save();

        $this->customer->refresh();

        // Refill UI
        $this->police_place_code = $this->customer->police_place_code;
        $this->birth_place_code  = $this->customer->birth_place_code;

        // ✅ FIX QUI
        $this->citizenship_place_code = $this->customer->citizenship_cargos_code;

        $this->birthdate = optional($this->customer->birthdate)->format('Y-m-d');
        $this->driver_license_expires_at = optional($this->customer->driver_license_expires_at)->format('Y-m-d');

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
