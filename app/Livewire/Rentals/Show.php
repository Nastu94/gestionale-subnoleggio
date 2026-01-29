<?php

namespace App\Livewire\Rentals;

use App\Models\Rental;
use App\Models\RentalChecklist;
use App\Models\Customer;
use App\Models\CargosLuogo;
use App\Models\CargosTransmission;
use App\Models\MediaEmailDelivery;
use App\Services\Cargos\CargosCheckService;
use App\Services\Cargos\CargosSendService;
use App\Services\Contracts\GenerateRentalContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Livewire: Scheda noleggio con tab e Action Drawer.
 *
 * NOTE:
 * - Questo componente gestisce sia il "Cliente principale" sia la "Seconda guida"
 *   usando un unico modale, con:
 *     - role: primary|second
 *     - mode: create|edit
 *
 * - Il form customer è allineato al Wizard (CARGOS-ready)
 *   e normalizza i luogo-picker che possono restituire array/stdClass.
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

    /* -------------------------------------------------------------------------
     |  MODALE CLIENTE / SECONDA GUIDA
     * ------------------------------------------------------------------------- */

    /** Modale aperto/chiuso */
    public bool $customerModalOpen = false;

    /**
     * Contesto modale (legacy/compat): 'primary'|'second'
     */
    public string $customerModalContext = 'primary';

    /** Ruolo del modale: primary (cliente) | second (seconda guida) */
    public string $customerRole = 'primary';

    /** Modalità: create (crea/associa) | edit (modifica anagrafica già collegata) */
    public string $customerModalMode = 'create';

    /** Se true, abbiamo selezionato un customer esistente (o stiamo editando). */
    public bool $customerPopulated = false;

    /** Id customer selezionato/in modifica */
    public ?int $customer_id = null;

    /**
     * Form customer (Wizard/CARGOS)
     *
     * ✅ Aggiunti i campi che in DB esistono e che vanno salvati come nel Wizard:
     * - doc_id_type
     * - driver_license_document_type_code
     * - birth_place
     * - city, province, country_code
     * - citizenship, citizenship_cargos_code
     */
    public array $customerForm = [
        // canonico
        'name' => null,

        // Wizard/CARGOS
        'first_name' => null,
        'last_name'  => null,
        'birth_date' => null,

        // Luoghi (CARGOS)
        'birth_place'      => null,
        'birth_place_code' => null,

        // UI virtuale (picker) -> in DB è citizenship_cargos_code
        'citizenship'            => null,
        'citizenship_place_code' => null,
        'citizenship_cargos_code'=> null,

        // Documento identità
        'identity_document_type_code' => null,
        'doc_id_type'                 => null, // enum interno
        'doc_id_number'               => null,
        'identity_document_place_code'=> null,

        // Patente
        'driver_license_document_type_code' => null,
        'driver_license_number'             => null,
        'driver_license_expires_at'         => null,
        'driver_license_place_code'         => null,

        // Fiscali
        'tax_code' => null,
        'vat'      => null,

        // Contatti
        'email' => null,
        'phone' => null,

        // Residenza (CARGOS)
        'police_place_code' => null,

        // Indirizzo testuale
        'address' => null,
        'zip'     => null,

        // Derivati (come nel Wizard)
        'city'         => null,
        'province'     => null,
        'country_code' => null,
    ];

    /** Ricerca customer esistenti (usata SOLO in mode=create) */
    public string $customerQuery = '';

    /* -------------------------------------------------------------------------
     |  PREZZO OVERRIDE
     * ------------------------------------------------------------------------- */

    /** Override prezzo finale (rentals.final_amount_override) */
    public ?string $final_amount_override = null;

    /** Cache nomi luoghi per evitare query ripetute */
    private array $cargosLuogoNameCache = [];

    public function mount(Rental $rental): void
    {
        $this->rental = $rental->load([
            'customer',
            'secondDriver',
            'vehicle',
            'checklists',
            'damages'
        ]);

        $this->final_amount_override = $this->rental->final_amount_override !== null
            ? (string) $this->rental->final_amount_override
            : null;
    }

    public function switch(string $tab): void
    {
        $this->tab = $tab;
    }

    /* -------------------------------------------------------------------------
     |  HELPERS: ruolo/mode + FK seconda guida
     * ------------------------------------------------------------------------- */

    protected function isSecondDriverContext(): bool
    {
        return $this->customerRole === 'second';
    }

    /**
     * Compat: in alcuni DB la colonna potrebbe chiamarsi:
     * - second_driver_customer_id (preferita)
     * - second_driver_id (legacy)
     */
    protected function secondDriverForeignKey(): string
    {
        $attrs = $this->rental->getAttributes();

        if (array_key_exists('second_driver_customer_id', $attrs)) {
            return 'second_driver_customer_id';
        }

        return 'second_driver_id';
    }

    protected function primaryForeignKey(): string
    {
        return 'customer_id';
    }

    protected function rentalForeignKeyForRole(): string
    {
        return $this->isSecondDriverContext()
            ? $this->secondDriverForeignKey()
            : $this->primaryForeignKey();
    }

    protected function resetCustomerForm(): void
    {
        $this->customerPopulated = false;
        $this->customer_id = null;

        foreach (array_keys($this->customerForm) as $key) {
            $this->customerForm[$key] = null;
        }
    }

    /** Normalizza spazi/trim */
    private function norm(?string $s): string
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return $s ?: '';
    }

    /** Ritorna null se stringa vuota, altrimenti trimmed. */
    private function nullIfBlank(mixed $v): ?string
    {
        if ($v === null) return null;
        if (!is_string($v)) return null;

        $t = trim($v);
        return $t === '' ? null : $t;
    }

    /** Calcola "Nome completo" da first_name + last_name e lo salva in customerForm.name */
    private function syncComputedCustomerName(): void
    {
        $first = $this->norm($this->customerForm['first_name'] ?? '');
        $last  = $this->norm($this->customerForm['last_name'] ?? '');
        $full  = $this->norm(trim($first . ' ' . $last));

        $this->customerForm['name'] = $full ?: null;
    }

    /**
     * Fallback: se ho solo name (vecchi record), provo a splittare in first/last.
     */
    private function splitNameIntoParts(?string $full): array
    {
        $full = $this->norm($full);
        if ($full === '') return [null, null];

        $parts = preg_split('/\s+/', $full, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 1) return [$parts[0], null];

        $first = array_shift($parts);
        $last  = implode(' ', $parts);

        return [$first ?: null, $last ?: null];
    }

    /** Recupera il nome del luogo CARGOS da code, con piccola cache in memoria. */
    private function cargosLuogoName(?int $code): ?string
    {
        if (!$code) return null;

        if (array_key_exists($code, $this->cargosLuogoNameCache)) {
            return $this->cargosLuogoNameCache[$code];
        }

        $luogo = CargosLuogo::find($code);
        $name = $luogo?->name;

        $this->cargosLuogoNameCache[$code] = $name ?: null;

        return $this->cargosLuogoNameCache[$code];
    }

    /**
     * Mappa i codici CARGOS dei tipi documento ai valori interni usati nell'app (doc_id_type enum).
     */
    private function mapCargosDocTypeToInternal(?string $cargosCode): ?string
    {
        return match ($cargosCode) {
            'IDENT', 'IDELE'                 => 'id',
            'PASDI', 'PASOR', 'PASSE'        => 'passport',
            'PATEN'                          => 'license',
            'CIDIP'                          => 'other',
            default                          => null,
        };
    }

    /**
     * Deriva campi testuali dalla residenza CARGOS (police_place_code), come nel Wizard:
     * - city, province, country_code
     *
     * NB: Se il place è una NAZIONE (province_code === 'ES'), city/province restano null.
     */
    private function deriveResidenceFromPolicePlaceCode(?int $policePlaceCode): array
    {
        $out = [
            'city'         => null,
            'province'     => null,
            'country_code' => null,
        ];

        if (!$policePlaceCode) return $out;

        /** @var CargosLuogo|null $luogo */
        $luogo = CargosLuogo::find($policePlaceCode);
        if (!$luogo) return $out;

        // NAZIONE (estero o Italia "come nazione"): province_code = 'ES'
        if (($luogo->province_code ?? null) === 'ES') {
            $cc = $luogo->country_code ?? null;
            $out['country_code'] = is_string($cc) && $cc !== '' ? substr($cc, 0, 2) : null;
            return $out;
        }

        // COMUNE (Italia)
        $out['city']         = $luogo->name;
        $out['province']     = $luogo->province_code;
        $out['country_code'] = 'IT';

        return $out;
    }

    protected function fillCustomerFormFromModel(Customer $c): void
    {
        $first = $c->first_name ?? null;
        $last  = $c->last_name ?? null;

        if ($this->norm($first) === '' && $this->norm($last) === '') {
            [$f, $l] = $this->splitNameIntoParts($c->name ?? null);
            $first = $f;
            $last  = $l;
        }

        $this->customerForm = array_merge($this->customerForm, [
            'name' => $c->name ?? null,

            'first_name' => $first,
            'last_name'  => $last,

            'birth_date' => optional($c->birth_date ?? $c->birthdate ?? null)->format('Y-m-d'),

            // ✅ nascita / cittadinanza (DB + picker)
            'birth_place'      => $c->birth_place ?? null,
            'birth_place_code' => $c->birth_place_code ?? null,

            'citizenship'             => $c->citizenship ?? null,
            'citizenship_cargos_code' => $c->citizenship_cargos_code ?? null,
            'citizenship_place_code'  => $c->citizenship_cargos_code ?? null, // il picker usa il code

            // ✅ documenti
            'identity_document_type_code'  => $c->identity_document_type_code ?? null,
            'doc_id_type'                  => $c->doc_id_type ?? null,
            'doc_id_number'                => $c->doc_id_number ?? null,
            'identity_document_place_code' => $c->identity_document_place_code ?? null,

            // ✅ patente
            'driver_license_document_type_code' => $c->driver_license_document_type_code ?? 'PATEN',
            'driver_license_number'             => $c->driver_license_number ?? null,
            'driver_license_expires_at'         => optional($c->driver_license_expires_at ?? null)->format('Y-m-d'),
            'driver_license_place_code'         => $c->driver_license_place_code ?? null,

            // fiscali
            'tax_code' => $c->tax_code ?? null,
            'vat'      => $c->vat ?? null,

            // contatti
            'email' => $c->email ?? null,
            'phone' => $c->phone ?? null,

            // residenza CARGOS
            'police_place_code' => $c->police_place_code ?? null,

            // indirizzo
            'address' => $c->address ?? $c->address_line ?? null,
            'zip'     => $c->zip ?? $c->postal_code ?? null,

            // derivati residenza
            'city'         => $c->city ?? null,
            'province'     => $c->province ?? null,
            'country_code' => $c->country_code ?? null,
        ]);

        $this->syncComputedCustomerName();
    }

    /* -------------------------------------------------------------------------
     |  VALIDAZIONE CUSTOMER
     * ------------------------------------------------------------------------- */

    /**
     * Regola custom per campi luogo-picker:
     * accetta string o array (il picker spesso produce array), evitando validation.string.
     */
    protected function placeRule(bool $required = false): array
    {
        return array_filter([
            $required ? 'required' : 'nullable',
            function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === null || $value === '') return;

                if (!is_string($value) && !is_array($value) && !is_int($value) && !is_numeric($value)) {
                    $fail('Valore non valido.');
                    return;
                }

                if (is_string($value) && mb_strlen(trim($value)) > 255) {
                    $fail('Valore troppo lungo.');
                    return;
                }

                if (is_array($value) && empty($value)) {
                    $fail('Valore non valido.');
                }
            },
        ]);
    }

    /**
     * Normalizza un "luogo" proveniente dal picker.
     * - Se il campo nel model è castato a json/array -> salva l'array
     * - Altrimenti prova ad estrarre una stringa/codice
     *
     * ✅ FIX: ora gestisce anche INT (code già normalizzato)
     */
    protected function normalizePlaceForStorage(Customer $customer, string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') return null;

        // ✅ se è già un code int (wizard-flow), lo teniamo
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            // Se il model ha cast json/array, possiamo salvare l'array
            if (method_exists($customer, 'hasCast') && $customer->hasCast($field, ['array', 'json', 'object', 'collection'])) {
                return $value;
            }

            // Prova chiavi tipiche
            foreach (['code', 'place_code', 'value', 'id'] as $k) {
                if (isset($value[$k]) && is_string($value[$k]) && trim($value[$k]) !== '') {
                    return trim($value[$k]);
                }
                if (isset($value[$k]) && (is_int($value[$k]) || is_numeric($value[$k]))) {
                    return (int) $value[$k];
                }
            }

            // Fallback leggibile
            $country = $value['country'] ?? $value['nation'] ?? $value['nazione'] ?? null;
            $prov    = $value['province'] ?? $value['provincia'] ?? null;
            $city    = $value['city'] ?? $value['comune'] ?? $value['municipality'] ?? null;

            $parts = array_values(array_filter([$country, $prov, $city], fn ($x) => is_string($x) && trim($x) !== ''));
            return $parts ? implode(' - ', array_map('trim', $parts)) : json_encode($value);
        }

        return null;
    }

    protected function customerRules(): array
    {
        // nel Wizard la residenza è richiesta; qui rendiamola:
        // - richiesta quando creo/associo (mode=create)
        // - nullable quando modifico record legacy già collegati (mode=edit)
        $residenceRule = ($this->customerModalMode === 'create')
            ? ['required', 'integer', 'exists:cargos_luoghi,code']
            : ['nullable', 'integer', 'exists:cargos_luoghi,code'];

        return [
            'customerForm.first_name' => ['required', 'string', 'max:191'],
            'customerForm.last_name'  => ['required', 'string', 'max:191'],

            'customerForm.name'  => ['required', 'string', 'max:255'],

            'customerForm.email' => ['required', 'email', 'max:191'],
            'customerForm.phone' => ['required', 'string', 'max:50'],

            'customerForm.birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],

            // ✅ CARGOS places (wizard-style)
            'customerForm.police_place_code'              => $residenceRule,
            'customerForm.birth_place_code'               => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
            'customerForm.citizenship_place_code'         => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
            'customerForm.identity_document_place_code'   => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
            'customerForm.driver_license_place_code'      => ['nullable', 'integer', 'exists:cargos_luoghi,code'],

            // Doc/patente
            'customerForm.identity_document_type_code' => ['nullable', 'string', 'max:64'],
            'customerForm.doc_id_number'               => ['nullable', 'string', 'max:100'],

            'customerForm.driver_license_number'       => ['nullable', 'string', 'max:64'],
            'customerForm.driver_license_expires_at'   => ['nullable', 'date', 'after:1900-01-01'],

            // Indirizzo
            'customerForm.address' => ['nullable', 'string', 'max:255'],
            'customerForm.zip'     => ['nullable', 'string', 'max:20'],

            // Fiscale
            'customerForm.tax_code' => ['nullable', 'string', 'max:32'],
            'customerForm.vat'      => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function customerMessages(): array
    {
        return [
            'customerForm.police_place_code.required' => 'Seleziona la residenza (luogo CARGOS).',
            'customerForm.police_place_code.integer'  => 'La residenza non è valida.',
            'customerForm.police_place_code.exists'   => 'La residenza selezionata non esiste.',

            'customerForm.birth_place_code.integer'             => 'Il luogo di nascita non è valido.',
            'customerForm.birth_place_code.exists'              => 'Il luogo di nascita selezionato non esiste.',
            'customerForm.citizenship_place_code.integer'       => 'La cittadinanza non è valida.',
            'customerForm.citizenship_place_code.exists'        => 'La cittadinanza selezionata non esiste.',
            'customerForm.identity_document_place_code.integer' => 'Il luogo rilascio documento non è valido.',
            'customerForm.identity_document_place_code.exists'  => 'Il luogo rilascio documento selezionato non esiste.',
            'customerForm.driver_license_place_code.integer'    => 'Il luogo rilascio patente non è valido.',
            'customerForm.driver_license_place_code.exists'     => 'Il luogo rilascio patente selezionato non esiste.',
        ];
    }

    /* -------------------------------------------------------------------------
     |  MODALE: OPEN/CLOSE
     * ------------------------------------------------------------------------- */

    public function openCustomerModal(string $role = 'primary'): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare i dati conducente dopo l’avvio del noleggio.');
            return;
        }

        $this->resetErrorBag();
        $this->resetValidation();

        $this->customerRole = ($role === 'second') ? 'second' : 'primary';
        $this->customerModalContext = $this->customerRole;

        if ($this->isSecondDriverContext() && empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'warning', message: 'Inserisci prima il cliente principale.');
            return;
        }

        $this->customerQuery = '';

        if ($this->isSecondDriverContext()) {
            $fk = $this->secondDriverForeignKey();
            $hasSecond = !empty($this->rental->{$fk}) && $this->rental->secondDriver;

            if ($hasSecond) {
                $this->customerModalMode = 'edit';
                $this->customerPopulated = true;
                $this->customer_id = (int) $this->rental->secondDriver->id;
                $this->fillCustomerFormFromModel($this->rental->secondDriver);
            } else {
                $this->customerModalMode = 'create';
                $this->resetCustomerForm();
            }
        } else {
            $hasPrimary = !empty($this->rental->customer_id) && $this->rental->customer;

            if ($hasPrimary) {
                $this->customerModalMode = 'edit';
                $this->customerPopulated = true;
                $this->customer_id = (int) $this->rental->customer->id;
                $this->fillCustomerFormFromModel($this->rental->customer);
            } else {
                $this->customerModalMode = 'create';
                $this->resetCustomerForm();
            }
        }

        $this->customerModalOpen = true;
    }

    public function closeCustomerModal(): void
    {
        $this->customerModalOpen = false;
    }

    /* -------------------------------------------------------------------------
     |  RIMOZIONE ASSOCIAZIONE CLIENTE / SECONDA GUIDA
     * ------------------------------------------------------------------------- */

    public function detachPrimaryCustomer(): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare i dati conducente in questa fase del noleggio.');
            return;
        }

        $fkSecond = $this->secondDriverForeignKey();
        if (!empty($this->rental->{$fkSecond})) {
            $this->dispatch('toast', type: 'warning', message: 'Rimuovi prima la seconda guida, poi potrai rimuovere il cliente principale.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->rental->customer_id = null;
                $this->rental->save();
            });

            $this->rental->refresh();
            $this->rental->load(['customer', 'secondDriver']);

            $this->customerModalOpen = false;
            $this->resetCustomerForm();

            $this->dispatch('toast', type: 'success', message: 'Cliente rimosso dal noleggio.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la rimozione del cliente.');
        }
    }

    public function detachSecondDriver(): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare i dati conducente in questa fase del noleggio.');
            return;
        }

        $fk = $this->secondDriverForeignKey();

        try {
            DB::transaction(function () use ($fk) {
                $this->rental->{$fk} = null;
                $this->rental->save();
            });

            $this->rental->refresh();
            $this->rental->load(['customer', 'secondDriver']);

            $this->syncRentalAmountFromSnapshot();

            $this->customerModalOpen = false;
            $this->resetCustomerForm();

            $this->dispatch('toast', type: 'success', message: 'Seconda guida rimossa dal noleggio.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la rimozione della seconda guida.');
        }
    }

    /* -------------------------------------------------------------------------
     |  SALVATAGGIO CUSTOMER (primary/second)
     * ------------------------------------------------------------------------- */

    public function createOrUpdateCustomer(): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare i dati conducente dopo l’avvio del noleggio.');
            return;
        }

        // name = first + last
        $this->syncComputedCustomerName();

        // ✅ Wizard-flow: trasformo i picker in INT code PRIMA di validare
        $this->normalizeCustomerCargosPlaceCodes();

        // ✅ validate con messaggi custom
        $this->validate($this->customerRules(), $this->customerMessages());

        // Normalizzazioni soft
        if (!empty($this->customerForm['tax_code'])) {
            $this->customerForm['tax_code'] = strtoupper(trim((string) $this->customerForm['tax_code']));
        }
        if (!empty($this->customerForm['vat'])) {
            $this->customerForm['vat'] = strtoupper(trim((string) $this->customerForm['vat']));
        }

        $fk = $this->rentalForeignKeyForRole();

        try {
            DB::transaction(function () use ($fk) {

                // 1) Upsert customer
                if ($this->customerPopulated === true && $this->customer_id) {
                    $customer = Customer::query()->findOrFail($this->customer_id);
                } else {
                    $customer = new Customer();
                    $customer->organization_id = $this->rental->organization_id;
                }

                // ✅ NORMALIZZA LUOGHI (adesso NON azzera più gli INT)
                foreach ([
                    'birth_place_code',
                    'citizenship_place_code',
                    'identity_document_place_code',
                    'driver_license_place_code',
                    'police_place_code',
                ] as $field) {
                    $this->customerForm[$field] = $this->normalizePlaceForStorage($customer, $field, $this->customerForm[$field] ?? null);
                }

                // ===== Wizard-like mapping / derivazioni =====

                $identityDocCargos = $this->nullIfBlank($this->customerForm['identity_document_type_code'] ?? null);

                // doc_id_type (enum interno) -> se non lo inserisci manualmente, lo deriviamo dal codice CARGOS
                $internalDocType = $this->nullIfBlank($this->customerForm['doc_id_type'] ?? null)
                    ?? $this->mapCargosDocTypeToInternal($identityDocCargos);

                $birthPlaceCode       = $this->toIntOrNull($this->customerForm['birth_place_code'] ?? null);
                $policePlaceCode      = $this->toIntOrNull($this->customerForm['police_place_code'] ?? null);
                $citizenshipPlaceCode = $this->toIntOrNull($this->customerForm['citizenship_place_code'] ?? null);

                $birthPlaceName = $this->cargosLuogoName($birthPlaceCode);
                $citizenshipName = $this->cargosLuogoName($citizenshipPlaceCode);

                $resDerived = $this->deriveResidenceFromPolicePlaceCode($policePlaceCode);

                // manteniamo coerente anche lo stato del form (utile se nel modale li mostri come readonly)
                $this->customerForm['birth_place'] = $birthPlaceName;
                $this->customerForm['citizenship'] = $citizenshipName;
                $this->customerForm['citizenship_cargos_code'] = $citizenshipPlaceCode;

                $this->customerForm['city']         = $resDerived['city']         ?? ($this->customerForm['city'] ?? null);
                $this->customerForm['province']     = $resDerived['province']     ?? ($this->customerForm['province'] ?? null);
                $this->customerForm['country_code'] = $resDerived['country_code'] ?? ($this->customerForm['country_code'] ?? null);

                $driverLicenseDocType = $this->nullIfBlank($this->customerForm['driver_license_document_type_code'] ?? null) ?? 'PATEN';

                // 2) Attributi (Wizard) + robustezza su colonne diverse
                $attrs = [
                    'name'       => $this->customerForm['name'],
                    'first_name' => $this->customerForm['first_name'],
                    'last_name'  => $this->customerForm['last_name'],

                    // date / nascita
                    'birthdate'        => $this->customerForm['birth_date'],
                    'birth_place_code' => $birthPlaceCode,
                    'birth_place'      => $birthPlaceName,

                    // cittadinanza
                    'citizenship'             => $citizenshipName,
                    'citizenship_cargos_code' => $citizenshipPlaceCode,

                    // documenti
                    'identity_document_type_code'  => $identityDocCargos,
                    'doc_id_type'                  => $internalDocType,
                    'doc_id_number'                => $this->customerForm['doc_id_number'] ?? null,
                    'identity_document_place_code' => $this->toIntOrNull($this->customerForm['identity_document_place_code'] ?? null),

                    // patente
                    'driver_license_document_type_code' => $driverLicenseDocType,
                    'driver_license_number'             => $this->customerForm['driver_license_number'] ?? null,
                    'driver_license_expires_at'         => $this->customerForm['driver_license_expires_at'] ?? null,
                    'driver_license_place_code'         => $this->toIntOrNull($this->customerForm['driver_license_place_code'] ?? null),

                    // fiscali / contatti
                    'tax_code' => $this->customerForm['tax_code'] ?? null,
                    'vat'      => $this->customerForm['vat'] ?? null,
                    'email'    => $this->customerForm['email'] ?? null,
                    'phone'    => $this->customerForm['phone'] ?? null,

                    // residenza CARGOS
                    'police_place_code' => $policePlaceCode,

                    // indirizzo
                    'address_line' => $this->customerForm['address'] ?? null,
                    'postal_code'  => $this->customerForm['zip'] ?? null,

                    // derivati residenza
                    'city'         => $this->customerForm['city'] ?? null,
                    'province'     => $this->customerForm['province'] ?? null,
                    'country_code' => $this->customerForm['country_code'] ?? null,

                    // compat vecchi nomi (se presenti in qualche env)
                    'address' => $this->customerForm['address'] ?? null,
                    'zip'     => $this->customerForm['zip'] ?? null,
                    'birth_date' => $this->customerForm['birth_date'] ?? null,
                ];

                // ✅ Filtra in base alle colonne effettive presenti (così non esplode se manca qualcosa)
                $cols = Schema::getColumnListing($customer->getTable());
                $attrs = array_intersect_key($attrs, array_flip($cols));

                // usa forceFill per robustezza
                $customer->forceFill($attrs)->save();

                // 3) Business rule: seconda guida != cliente principale
                if ($this->isSecondDriverContext() && !empty($this->rental->customer_id)) {
                    if ((int) $this->rental->customer_id === (int) $customer->id) {
                        throw new \RuntimeException('La seconda guida non può coincidere con il cliente principale.');
                    }
                }

                // 4) Associazione sul rental
                $this->rental->{$fk} = (int) $customer->id;
                $this->rental->save();
            });

            $this->rental->refresh();
            $this->rental->load(['customer', 'secondDriver']);

            if ($this->isSecondDriverContext()) {
                $this->syncRentalAmountFromSnapshot();
            }

            $this->customerModalOpen = false;

            $msg = $this->isSecondDriverContext()
                ? ($this->customerModalMode === 'edit' ? 'Seconda guida aggiornata.' : 'Seconda guida associata al noleggio.')
                : ($this->customerModalMode === 'edit' ? 'Cliente aggiornato.' : 'Cliente associato al noleggio.');

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('$refresh');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante il salvataggio del cliente.');
        }
    }

    /* -------------------------------------------------------------------------
     |  RICERCA CUSTOMER (SOLO mode=create)
     * ------------------------------------------------------------------------- */

    public function getCustomerSearchResultsProperty(): array
    {
        $this->authorize('update', $this->rental);

        if (!$this->customerModalOpen || $this->customerModalMode !== 'create') {
            return [];
        }

        if (mb_strlen($this->customerQuery) < 2) {
            return [];
        }

        $q = trim($this->customerQuery);

        return Customer::query()
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

    public function selectCustomer(int $id): void
    {
        $this->authorize('update', $this->rental);

        if ($this->customerModalMode !== 'create') {
            return;
        }

        $customer = Customer::query()->findOrFail($id);

        $this->customer_id = (int) $customer->id;
        $this->customerPopulated = true;

        $this->fillCustomerFormFromModel($customer);
        $this->syncComputedCustomerName();

        $this->customerQuery = '';
    }

    /**
     * Trova lo snapshot pricing dal media del contratto (firmato > non firmato).
     */
    private function getContractPricingSnapshot(): ?array
    {
        if (!method_exists($this->rental, 'getMedia')) return null;

        foreach (['signatures', 'contract'] as $col) {
            $items = $this->rental->getMedia($col)->sortByDesc('created_at');

            foreach ($items as $m) {
                $snap = $m->getCustomProperty('pricing_snapshot');
                if (is_array($snap)) return $snap;
            }
        }

        return null;
    }

    /**
     * Aggiorna rentals.amount = tariffa (congelata) + seconda guida (se presente).
     */
    private function syncRentalAmountFromSnapshot(): void
    {
        if (!in_array($this->rental->status, ['draft','reserved'], true)) {
            return;
        }

        $snap = $this->getContractPricingSnapshot();

        $days = (int) ($snap['days'] ?? 0);
        $tariffTotalCents = isset($snap['tariff_total_cents']) ? (int) $snap['tariff_total_cents'] : null;

        if ($tariffTotalCents === null && $this->rental->amount !== null) {
            $tariffTotalCents = (int) round(((float)$this->rental->amount) * 100);
        }

        if ($tariffTotalCents === null) {
            return;
        }

        $days = max(1, $days ?: 1);

        $secondDailyCents = (int) ($snap['second_driver_daily_cents'] ?? 0);

        $fk = $this->secondDriverForeignKey();
        $hasSecond = !empty($this->rental->{$fk});

        $secondTotalCents = $hasSecond ? ($secondDailyCents * $days) : 0;
        $computedCents = $tariffTotalCents + $secondTotalCents;

        DB::transaction(function () use ($computedCents) {
            $this->rental->amount = round($computedCents / 100, 2);
            $this->rental->save();
        });

        $this->rental->refresh();
    }

    /* -------------------------------------------------------------------------
     |  CONTRATTO / PREZZO OVERRIDE
     * ------------------------------------------------------------------------- */

    protected function finalAmountRules(): array
    {
        return [
            'final_amount_override' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Reinvio manuale del contratto firmato via email al cliente.
     *
     * - Recupera l'ultimo Media della collection "signatures" (fallback: "rental-contract-signed")
     * - Aggiorna/crea la riga in media_email_deliveries (documento logico = Rental + collection)
     * - Invia email con allegato PDF
     * - Registra esito e eventuali errori
     */
    public function resendSignedContractEmail(): void
    {
        $this->authorize('update', $this->rental);

        $this->rental->loadMissing(['customer']);
        $customer = $this->rental->customer;

        if (!$customer || empty($customer->email)) {
            $this->dispatch('toast', type: 'error', message: 'Cliente assente o senza email: impossibile inviare il contratto.');
            return;
        }

        $adminEmail = (string) config('rentals.admin_email');

        // Recupera ultimo contratto firmato (principale + fallback compat)
        $media = null;

        if (method_exists($this->rental, 'getMedia')) {
            $media = $this->rental->getMedia('signatures')->sortByDesc('created_at')->first();

            if (!$media) {
                $media = $this->rental->getMedia('rental-contract-signed')->sortByDesc('created_at')->first();
            }
        }

        if (!$media) {
            $this->dispatch('toast', type: 'warning', message: 'Nessun contratto firmato trovato da inviare.');
            return;
        }

        // Documento logico = Rental + collection "signatures"
        $docModelType  = Rental::class;
        $docModelId    = (int) $this->rental->id;
        $docCollection = 'signatures';

        $delivery = MediaEmailDelivery::query()->firstOrNew([
            'model_type'      => $docModelType,
            'model_id'        => $docModelId,
            'collection_name' => $docCollection,
        ]);

        $delivery->recipient_email  = (string) $customer->email;
        $delivery->current_media_id = (int) $media->getKey();
        $delivery->status              = MediaEmailDelivery::STATUS_RESEND_REQUESTED;
        $delivery->resend_requested_at = now();
        $delivery->save();

        $rentalLabel = $this->rental->reference ?? $this->rental->display_number_label ?? ('#' . $this->rental->id);

        $subject = 'Contratto firmato - Noleggio ' . $rentalLabel;
        $body = "In allegato trovi il contratto firmato relativo al tuo noleggio ({$rentalLabel}).\n\n"
            . "Se non riconosci questa email, contatta l'assistenza.";

        try {
            // Tracking tentativo PRIMA dell'invio (cliente)
            $delivery->send_attempts      = (int) $delivery->send_attempts + 1;
            $delivery->last_attempt_at    = now();
            $delivery->last_error_message = null;
            $delivery->save();

            $disk = $media->disk;
            $path = $media->getPathRelativeToRoot();

            if (!Storage::disk($disk)->exists($path)) {
                throw new \RuntimeException("File non trovato su storage: disk={$disk}, path={$path}");
            }

            // === INVIO CLIENTE (TRACCIATO) ===
            Mail::raw($body, function ($message) use ($customer, $subject, $media, $disk, $path): void {
                $message
                    ->to($customer->email)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));

                // Evita RAM (preferibile)
                if (method_exists($message, 'attachFromStorageDisk')) {
                    $message->attachFromStorageDisk($disk, $path, $media->file_name, ['mime' => $media->mime_type]);
                } else {
                    // fallback (meno efficiente)
                    $fileContents = Storage::disk($disk)->get($path);
                    $message->attachData($fileContents, $media->file_name, ['mime' => $media->mime_type]);
                }
            });

            // Success tracking (cliente)
            if (is_null($delivery->first_sent_at)) {
                $delivery->first_sent_at = now();
                if (is_null($delivery->first_media_id)) {
                    $delivery->first_media_id = (int) $media->getKey();
                }
            }

            $delivery->last_sent_at       = now();
            $delivery->last_sent_media_id = (int) $media->getKey();
            $delivery->status             = MediaEmailDelivery::STATUS_SENT;
            $delivery->save();

            // === INVIO ADMIN (NON TRACCIATO, NON BLOCCANTE) ===
            if (!empty($adminEmail)) {
                try {
                    $adminSubject = '[ADMIN] ' . $subject;

                    $adminBody = "È stato reinviato un contratto firmato.\n\n"
                        . "Noleggio: {$rentalLabel}\n"
                        . "Cliente: " . ($customer->name ?? '—') . "\n"
                        . "Email cliente: " . ($customer->email ?? '—') . "\n\n"
                        . "Documento in allegato.";

                    Mail::raw($adminBody, function ($message) use ($adminEmail, $adminSubject, $media, $disk, $path): void {
                        $message
                            ->to($adminEmail)
                            ->subject($adminSubject)
                            ->from(config('mail.from.address'), config('mail.from.name'));

                        if (method_exists($message, 'attachFromStorageDisk')) {
                            $message->attachFromStorageDisk($disk, $path, $media->file_name, ['mime' => $media->mime_type]);
                        } else {
                            $fileContents = Storage::disk($disk)->get($path);
                            $message->attachData($fileContents, $media->file_name, ['mime' => $media->mime_type]);
                        }
                    });
                } catch (\Throwable $ignored) {
                    // volutamente ignorato
                }
            }

            $this->dispatch('toast', type: 'success', message: 'Email inviata con il contratto firmato.');
        } catch (\Throwable $e) {
            report($e);

            $delivery->status             = MediaEmailDelivery::STATUS_FAILED;
            $delivery->last_error_message = $e->getMessage();
            $delivery->save();

            $debugMsg = config('app.debug')
                ? ('Errore invio email: ' . $e->getMessage())
                : 'Errore durante l’invio della mail con il contratto firmato.';

            $this->dispatch('toast', type: 'error', message: $debugMsg);
        }
    }

    /**
     * Salva l'override del prezzo finale.
     */
    public function saveFinalAmountOverride(): void
    {
        $this->authorize('update', $this->rental);

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non è possibile modificare il prezzo in questa fase del noleggio.');
            return;
        }

        $this->validate($this->finalAmountRules());

        $raw = is_string($this->final_amount_override) ? trim($this->final_amount_override) : $this->final_amount_override;
        $value = ($raw === '' || $raw === null) ? null : round((float) $raw, 2);

        try {
            DB::transaction(function () use ($value) {
                $this->rental->final_amount_override = $value;
                $this->rental->save();
            });

            $this->rental->refresh();

            $this->final_amount_override = $this->rental->final_amount_override !== null
                ? (string) $this->rental->final_amount_override
                : null;

            $this->dispatch('rental-amount-updated', base_amount: (float) ($this->rental->final_amount_override ?? $this->rental->amount ?? 0));
            $this->dispatch('toast', type: 'success', message: 'Prezzo aggiornato.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante il salvataggio del prezzo.');
        }
    }

    public function clearFinalAmountOverride(): void
    {
        $this->final_amount_override = null;
        $this->saveFinalAmountOverride();
    }

    public function generateContract(GenerateRentalContract $generator): void
    {
        if (!auth()->user()?->can('rentals.contract.generate') || !auth()->user()?->can('media.attach.contract')) {
            $this->dispatch('toast', type: 'error', message: 'Permesso negato.');
            return;
        }

        if (empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'error', message: 'Associa prima un cliente al noleggio.');
            return;
        }

        if (!in_array($this->rental->status, ['draft', 'reserved'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Non puoi generare il contratto in questa fase del noleggio.');
            return;
        }

        try {
            $generator->handle($this->rental, null, null, null, true, false);

            if ($this->rental->status === 'draft') {
                $this->rental->status = 'reserved';
                $this->rental->save();
            }

            $this->rental->refresh();
            $this->dispatch('toast', type: 'success', message: 'Contratto base generato.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la generazione del contratto.');
        }
    }

    public function regenerateContractWithSignatures(GenerateRentalContract $generator): void
    {
        $this->authorize('contractGenerate', $this->rental);

        if (empty($this->rental->customer_id)) {
            $this->dispatch('toast', type: 'warning', message: 'Associa prima un cliente al noleggio.');
            return;
        }

        if (!$this->hasCustomerSignature()) {
            $this->dispatch('toast', type: 'warning', message: 'Inserisci prima la firma del cliente per generare il contratto firmato.');
            return;
        }

        try {
            $hasBase = method_exists($this->rental, 'getMedia')
                && $this->rental->getMedia('contract')->isNotEmpty();

            if (!$hasBase) {
                $generator->handle($this->rental, null, null, null, true, false);
            }

            $generator->handle($this->rental, null, null, null, false, true);

            try {
                $this->ensurePickupChecklistSignedPdf();
            } catch (\Throwable $e) {
                report($e);
                $this->dispatch('toast', type: 'warning', message: 'Contratto firmato generato, ma non sono riuscito a generare la checklist pickup firmata.');
            }

            $this->rental->refresh();

            $this->dispatch('toast', type: 'success', message: 'Contratto firmato generato.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la generazione del contratto firmato.');
        }
    }

    private function hasCustomerSignature(): bool
    {
        return method_exists($this->rental, 'getFirstMedia')
            && (bool) $this->rental->getFirstMedia('signature_customer');
    }

    /* -------------------------------------------------------------------------
     |  CHECKLIST EVENTS
     * ------------------------------------------------------------------------- */

    public function generateSignedChecklistPdf(int $checklistId): void
    {
        $tmpRelPath = null;

        try {
            $checklist = RentalChecklist::with([
                'rental.organization',
                'rental.customer',
                'rental.vehicle',
            ])->findOrFail($checklistId);

            if ($checklist->isLocked()) {
                $this->dispatch('toast', type: 'warning', message: 'Checklist già bloccata: rimuovi il firmato per rigenerare.');
                return;
            }

            $rental = $checklist->rental;

            $customerSig = method_exists($rental, 'getFirstMedia')
                ? $rental->getFirstMedia('signature_customer')
                : null;

            $lessorOverrideSig = method_exists($rental, 'getFirstMedia')
                ? $rental->getFirstMedia('signature_lessor')
                : null;

            $lessorDefaultSig  = ($rental->organization && method_exists($rental->organization, 'getFirstMedia'))
                ? $rental->organization->getFirstMedia('signature_company')
                : null;

            $lessorSig = $lessorOverrideSig ?: $lessorDefaultSig;

            if (!$customerSig || !$lessorSig) {
                $this->dispatch('toast', type: 'error', message: 'Per generare il firmato servono firma cliente e firma noleggiante.');
                return;
            }

            $payload = $this->buildChecklistPayload($checklist);

            $signatures = [
                'customer' => $this->mediaToDataUri($customerSig),
                'lessor'   => $this->mediaToDataUri($lessorSig),
            ];

            $generated_at = now();

            $pdf = Pdf::loadView('pdfs.checklist', [
                'checklist'    => $checklist,
                'payload'      => $payload,
                'generated_at' => $generated_at,
                'signatures'   => $signatures,
            ])->setPaper('a4');

            Storage::makeDirectory('tmp');
            $tmpRelPath = "tmp/checklist-{$checklist->id}-signed.pdf";
            $tmpAbsPath = Storage::path($tmpRelPath);

            file_put_contents($tmpAbsPath, $pdf->output());

            $signedCollection = $checklist->signedCollectionName();

            DB::transaction(function () use ($checklist, $signedCollection, $tmpAbsPath) {
                $checklist->clearMediaCollection($signedCollection);

                /** @var Media $media */
                $media = $checklist
                    ->addMedia($tmpAbsPath)
                    ->usingName("checklist-{$checklist->type}-signed")
                    ->usingFileName("checklist-{$checklist->type}-{$checklist->id}-signed.pdf")
                    ->toMediaCollection($signedCollection);

                $checklist->forceFill([
                    'signed_media_id'   => $media->id,
                    'locked_at'         => now(),
                    'locked_by_user_id' => auth()->id(),
                    'locked_reason'     => 'generated_signed_pdf',
                ])->save();
            });

            Storage::delete($tmpRelPath);

            $this->dispatch('toast', type: 'success', message: 'Checklist firmata generata con successo.');
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            report($e);

            if ($tmpRelPath) {
                try { Storage::delete($tmpRelPath); } catch (\Throwable $ignored) {}
            }

            $debugMsg = config('app.debug')
                ? ('Errore PDF: ' . $e->getMessage())
                : 'Errore durante la generazione del PDF firmato.';

            $this->dispatch('toast', type: 'error', message: $debugMsg);
        }
    }

    private function buildChecklistPayload(RentalChecklist $checklist): array
    {
        $checklist->loadMissing([
            'rental.damages',
            'rental.vehicle.damages.firstRentalDamage',
        ]);

        $base = [
            'mileage'      => $checklist->mileage !== null ? (int) $checklist->mileage : 0,
            'fuel_percent' => $checklist->fuel_percent !== null ? (int) $checklist->fuel_percent : 0,
            'cleanliness'  => $checklist->cleanliness ?: null,
        ];

        $json = $this->normalizeChecklistJson($checklist->checklist_json);
        $damages = $this->buildChecklistDamages($checklist);

        return [
            'base'    => $base,
            'json'    => $json,
            'damages' => $damages,
        ];
    }

    private function normalizeChecklistJson(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $raw['vehicle']   = isset($raw['vehicle']) && is_array($raw['vehicle']) ? $raw['vehicle'] : [];
        $raw['documents'] = isset($raw['documents']) && is_array($raw['documents']) ? $raw['documents'] : [];
        $raw['equipment'] = isset($raw['equipment']) && is_array($raw['equipment']) ? $raw['equipment'] : [];
        $raw['notes']     = isset($raw['notes']) ? (string) $raw['notes'] : '';

        return $raw;
    }

    private function ensurePickupChecklistSignedPdf(): void
    {
        $this->rental->loadMissing(['checklists.media']);

        $pickup = $this->rental->checklists
            ->where('type', 'pickup')
            ->sortByDesc('created_at')
            ->first();

        if (!$pickup) {
            return;
        }

        if (method_exists($pickup, 'getMedia')) {
            if ($pickup->getMedia('checklist_pickup_signed')->isNotEmpty()) {
                return;
            }
        }

        if (method_exists($pickup, 'isLocked') && $pickup->isLocked()) {
            return;
        }

        $this->generateSignedChecklistPdf((int) $pickup->id);
    }

    private function buildChecklistDamages(RentalChecklist $checklist): array
    {
        $rental  = $checklist->rental;
        $vehicle = $rental?->vehicle;

        $vehicleRows = collect();

        if ($vehicle) {
            $vehicleOpen = $vehicle->damages
                ? $vehicle->damages->where('is_open', true)
                : collect();

            $vehicleRows = $vehicleOpen
                ->filter(function ($vd) use ($rental) {
                    $first = $vd->firstRentalDamage ?? null;
                    return !($first && (int) $first->rental_id === (int) $rental->id);
                })
                ->map(function ($vd) {
                    $area = (string) ($vd->resolved_area ?? $vd->area ?? 'other');
                    $severity = (string) ($vd->resolved_severity ?? $vd->severity ?? 'low');
                    $desc = trim((string) ($vd->resolved_description ?? $vd->description ?? '')) ?: '—';

                    return [
                        'area'        => $area ?: 'other',
                        'severity'    => $severity ?: 'low',
                        'description' => $desc,
                    ];
                })
                ->toBase();
        }

        $allowedPhases = $checklist->type === 'pickup'
            ? ['pickup']
            : ['pickup', 'during', 'return'];

        $rentalRows = ($rental?->damages ?? collect())
            ->filter(function ($rd) use ($allowedPhases) {
                $phase = (string) ($rd->phase ?? '');
                return $phase === '' || in_array($phase, $allowedPhases, true);
            })
            ->map(function ($rd) {
                $area = (string) ($rd->area ?? 'other');
                $severity = (string) ($rd->severity ?? 'low');
                $desc = trim((string) ($rd->description ?? '')) ?: '—';

                return [
                    'area'        => $area ?: 'other',
                    'severity'    => $severity ?: 'low',
                    'description' => $desc,
                ];
            })
            ->toBase();

        return $vehicleRows
            ->merge($rentalRows)
            ->filter(fn($r) => !empty($r['area']) || !empty($r['description']))
            ->unique(fn($r) => ($r['area'] ?? '').'|'.($r['severity'] ?? '').'|'.($r['description'] ?? ''))
            ->values()
            ->all();
    }

    private function mediaToDataUri(?Media $media): ?string
    {
        if (!$media) return null;

        $path = $media->getPath();
        if (!is_file($path)) return null;

        $mime = $media->mime_type ?: (function_exists('mime_content_type') ? mime_content_type($path) : null) ?: 'image/png';
        $data = base64_encode(file_get_contents($path));

        return "data:{$mime};base64,{$data}";
    }

    /**
     * Normalizza il nome della collection "signed" (compat con naming errati legacy).
     */
    private function normalizeSignedChecklistCollection(string $collectionName): string
    {
        return match ($collectionName) {
            'checklists_return_signed' => 'checklist_return_signed',
            default => $collectionName,
        };
    }

    /**
     * Reinvio manuale via email della checklist firmata (pickup/return) al cliente.
     *
     * - Recupera la checklist dal DB e verifica appartenenza al rental corrente
     * - Trova l'ultimo Media della collection firmata (signedCollectionName)
     * - Aggiorna/crea la riga in media_email_deliveries (documento logico = Checklist + collection)
     * - Invia email con allegato
     * - Registra esito e eventuali errori
     */
    public function resendSignedChecklistEmail(int $checklistId): void
    {
        $this->authorize('update', $this->rental);

        /** @var \App\Models\RentalChecklist $checklist */
        $checklist = RentalChecklist::query()
            ->with(['rental.customer'])
            ->findOrFail($checklistId);

        if ((int) $checklist->rental_id !== (int) $this->rental->id) {
            $this->dispatch('toast', type: 'error', message: 'Checklist non valida per questo noleggio.');
            return;
        }

        $customer = $checklist->rental?->customer;

        if (!$customer || empty($customer->email)) {
            $this->dispatch('toast', type: 'error', message: 'Cliente assente o senza email: impossibile inviare la checklist.');
            return;
        }

        $adminEmail = (string) config('rentals.admin_email');

        $signedCollection = method_exists($checklist, 'signedCollectionName')
            ? (string) $checklist->signedCollectionName()
            : ((string) ($checklist->type ?? '') === 'pickup' ? 'checklist_pickup_signed' : 'checklist_return_signed');

        $signedCollection = $this->normalizeSignedChecklistCollection($signedCollection);

        $media = null;
        if (method_exists($checklist, 'getMedia')) {
            $media = $checklist->getMedia($signedCollection)->sortByDesc('created_at')->first();

            if (!$media && $signedCollection === 'checklist_return_signed') {
                $media = $checklist->getMedia('checklists_return_signed')->sortByDesc('created_at')->first();
            }
        }

        if (!$media) {
            $this->dispatch('toast', type: 'warning', message: 'Nessuna checklist firmata trovata da inviare.');
            return;
        }

        $docModelType  = RentalChecklist::class;
        $docModelId    = (int) $checklist->id;
        $docCollection = $signedCollection;

        $delivery = MediaEmailDelivery::query()->firstOrNew([
            'model_type'      => $docModelType,
            'model_id'        => $docModelId,
            'collection_name' => $docCollection,
        ]);

        $delivery->recipient_email  = (string) $customer->email;
        $delivery->current_media_id = (int) $media->getKey();
        $delivery->status              = MediaEmailDelivery::STATUS_RESEND_REQUESTED;
        $delivery->resend_requested_at = now();
        $delivery->save();

        $rentalLabel = $this->rental->reference ?? $this->rental->display_number_label ?? ('#' . $this->rental->id);

        $subject = match ((string) ($checklist->type ?? '')) {
            'pickup' => 'Checklist di consegna firmata - Noleggio ' . $rentalLabel,
            'return' => 'Checklist di rientro firmata - Noleggio ' . $rentalLabel,
            default  => 'Checklist firmata - Noleggio ' . $rentalLabel,
        };

        $body = "In allegato trovi la checklist firmata relativa al tuo noleggio ({$rentalLabel}).\n\n"
            . "Se non riconosci questa email, contatta l'assistenza.";

        try {
            $delivery->send_attempts      = (int) $delivery->send_attempts + 1;
            $delivery->last_attempt_at    = now();
            $delivery->last_error_message = null;
            $delivery->save();

            $disk = $media->disk;
            $path = $media->getPathRelativeToRoot();

            if (!Storage::disk($disk)->exists($path)) {
                throw new \RuntimeException("File non trovato su storage: disk={$disk}, path={$path}");
            }

            // === INVIO CLIENTE (TRACCIATO) ===
            Mail::raw($body, function ($message) use ($customer, $subject, $media, $disk, $path): void {
                $message
                    ->to($customer->email)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));

                if (method_exists($message, 'attachFromStorageDisk')) {
                    $message->attachFromStorageDisk($disk, $path, $media->file_name, ['mime' => $media->mime_type]);
                } else {
                    $fileContents = Storage::disk($disk)->get($path);
                    $message->attachData($fileContents, $media->file_name, ['mime' => $media->mime_type]);
                }
            });

            if (is_null($delivery->first_sent_at)) {
                $delivery->first_sent_at = now();
                if (is_null($delivery->first_media_id)) {
                    $delivery->first_media_id = (int) $media->getKey();
                }
            }

            $delivery->last_sent_at       = now();
            $delivery->last_sent_media_id = (int) $media->getKey();
            $delivery->status             = MediaEmailDelivery::STATUS_SENT;
            $delivery->save();

            // === INVIO ADMIN (NON TRACCIATO, NON BLOCCANTE) ===
            if (!empty($adminEmail)) {
                try {
                    $adminSubject = '[ADMIN] ' . $subject;

                    $adminBody = "È stata reinviata una checklist firmata.\n\n"
                        . "Noleggio: {$rentalLabel}\n"
                        . "Checklist: #" . $checklist->id . " (" . ($checklist->type ?? '—') . ")\n"
                        . "Cliente: " . ($customer->name ?? '—') . "\n"
                        . "Email cliente: " . ($customer->email ?? '—') . "\n\n"
                        . "Documento in allegato.";

                    Mail::raw($adminBody, function ($message) use ($adminEmail, $adminSubject, $media, $disk, $path): void {
                        $message
                            ->to($adminEmail)
                            ->subject($adminSubject)
                            ->from(config('mail.from.address'), config('mail.from.name'));

                        if (method_exists($message, 'attachFromStorageDisk')) {
                            $message->attachFromStorageDisk($disk, $path, $media->file_name, ['mime' => $media->mime_type]);
                        } else {
                            $fileContents = Storage::disk($disk)->get($path);
                            $message->attachData($fileContents, $media->file_name, ['mime' => $media->mime_type]);
                        }
                    });
                } catch (\Throwable $ignored) {
                    // ignora
                }
            }

            $this->dispatch('toast', type: 'success', message: 'Email inviata con la checklist firmata.');
        } catch (\Throwable $e) {
            report($e);

            $delivery->status             = MediaEmailDelivery::STATUS_FAILED;
            $delivery->last_error_message = $e->getMessage();
            $delivery->save();

            $debugMsg = config('app.debug')
                ? ('Errore invio email checklist: ' . $e->getMessage())
                : 'Errore durante l’invio della mail con la checklist firmata.';

            $this->dispatch('toast', type: 'error', message: $debugMsg);
        }
    }

    /**
     * Livewire/JS può idratare valori complessi come stdClass.
     * Qui li convertiamo in array ricorsivamente.
     */
    private function deepToArray(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->deepToArray($v);
            }
        }

        return $value;
    }

    /**
     * Cast “robusto” a int per codici CARGOS:
     * - accetta int/string numeriche
     * - ritorna null per valori vuoti/non numerici
     */
    protected function toIntOrNull(mixed $v): ?int
    {
        if ($v === null) return null;

        if (is_int($v)) return $v;

        if (is_string($v)) {
            $t = trim($v);
            if ($t === '' || !ctype_digit($t)) return null;
            return (int) $t;
        }

        if (is_numeric($v)) return (int) $v;

        return null;
    }

    /**
     * Estrae il code CARGOS da quello che il picker può restituire
     * (int, string numerica, array, stdClass con dentro "code"/"id"/"value"...).
     */
    private function extractCargosCode(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;

        // stdClass -> array (ricorsivo)
        $value = $this->deepToArray($value);

        // caso semplice
        $direct = $this->toIntOrNull($value);
        if ($direct !== null) return $direct;

        // caso array: prova varie chiavi/path comuni
        if (is_array($value)) {
            $candidates = [
                data_get($value, 'code'),
                data_get($value, 'place_code'),
                data_get($value, 'id'),
                data_get($value, 'value'),

                // path annidati tipici
                data_get($value, 'selected.code'),
                data_get($value, 'selected.place_code'),
                data_get($value, 'item.code'),
                data_get($value, 'luogo.code'),
                data_get($value, 'data.code'),
            ];

            foreach ($candidates as $c) {
                $int = $this->toIntOrNull($c);
                if ($int !== null) return $int;
            }
        }

        return null;
    }

    /**
     * Normalizza i campi luogo-picker in "code" INT prima della validazione,
     * replicando il flusso del Wizard.
     */
    private function normalizeCustomerCargosPlaceCodes(): void
    {
        foreach ([
            'birth_place_code',
            'citizenship_place_code',
            'identity_document_place_code',
            'driver_license_place_code',
            'police_place_code',
        ] as $field) {
            $this->customerForm[$field] = $this->extractCargosCode($this->customerForm[$field] ?? null);
        }
    }

    /* -------------------------------------------------------------------------
     |  CARGOS: CHECK / SEND (UI actions)
     * ------------------------------------------------------------------------- */

    /**
     * CARGOS abilitato finché il noleggio non è chiuso o annullato.
     */
    protected function cargosEnabled(): bool
    {
        return !in_array($this->rental->status, ['closed', 'cancelled', 'canceled', 'no_show'], true);
    }

    public function getCargosLastCheckProperty(): ?CargosTransmission
    {
        return CargosTransmission::query()
            ->where('action', 'check')
            ->where('rental_id', $this->rental->id)
            ->latest('id')
            ->first();
    }

    public function getCargosLastSendProperty(): ?CargosTransmission
    {
        return CargosTransmission::query()
            ->where('action', 'send')
            ->where('rental_id', $this->rental->id)
            ->latest('id')
            ->first();
    }

    public function cargosCheck(CargosCheckService $svc): void
    {
        $this->authorize('update', $this->rental);

        if (!$this->cargosEnabled()) {
            $this->dispatch('toast', type: 'error', message: 'CARGOS: non disponibile su noleggi chiusi o annullati.');
            return;
        }

        $res = $svc->checkRental((int) $this->rental->id, auth()->user());

        if (($res['ok'] ?? false) === true) {
            $this->dispatch('toast', type: 'success', message: 'CARGOS: verifica completata con successo.');
            return;
        }

        $msg = (string) (($res['errors'][0] ?? null) ?: 'CARGOS: verifica non riuscita.');
        $this->dispatch('toast', type: 'error', message: $msg);
    }

    public function cargosSend(CargosSendService $svc): void
    {
        $this->authorize('update', $this->rental);

        if (!$this->cargosEnabled()) {
            $this->dispatch('toast', type: 'error', message: 'CARGOS: non disponibile su noleggi chiusi o annullati.');
            return;
        }

        $res = $svc->sendRental((int) $this->rental->id, auth()->user());

        if (($res['ok'] ?? false) === true) {
            $isDry = (bool) ($res['dry_run'] ?? false);

            $this->dispatch(
                'toast',
                type: 'success',
                message: $isDry ? 'CARGOS: invio simulato completato (dry-run).' : 'CARGOS: invio completato con successo.'
            );
            return;
        }

        $msg = (string) (($res['errors'][0] ?? null) ?: 'CARGOS: invio non riuscito.');
        $this->dispatch('toast', type: 'error', message: $msg);
    }

    #[On('checklist-signed-uploaded')]
    public function onChecklistSignedUploaded(array $payload = []): void
    {
        if (isset($payload['rentalId']) && (int) $payload['rentalId'] !== (int) $this->rental->id) {
            return;
        }

        $this->rental->refresh();
        $this->rental->load(['checklists.media']);

        $this->dispatch('$refresh');
    }

    #[On('checklist-media-updated')]
    public function onChecklistMediaUpdated(): void
    {
        $this->rental->refresh();
        $this->rental->load(['checklists.media']);
        $this->dispatch('$refresh');
    }

    #[On('signature-updated')]
    public function onSignatureUpdated(): void
    {
        $this->rental->refresh();
        $this->rental->load(['customer', 'secondDriver']);
        $this->dispatch('$refresh');
    }

    public function render(): View
    {
        return view('livewire.rentals.show');
    }
}
