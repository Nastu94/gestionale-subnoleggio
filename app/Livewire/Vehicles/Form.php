<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use App\Models\Location;
use App\Models\CargosVehicleType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    // Il veicolo in modalità EDIT, null in CREATE
    public ?Vehicle $vehicle = null;

    // Stato del form
    public array $form = [
        'admin_organization_id'      => null, // impostati solo backend
        'vin'                        => null,
        'plate'                      => '',
        'make'                       => '',
        'model'                      => '',
        'year'                       => null,
        'color'                      => null,
        'fuel_type'                  => 'petrol',
        'transmission'               => 'manual',
        'seats'                      => null,
        'segment'                    => null,
        'cargos_vehicle_type_code' => null,
        'mileage_current'            => 0,
        'default_pickup_location_id' => null, // impostati solo backend
        'is_active'                  => 1,    // impostati solo backend
        'notes'                      => null,

        // 👇 Aggiunte UI (in euro)
        'lt_rental_monthly_eur'      => null,
        'insurance_kasko_eur'        => null,
        'insurance_rca_eur'          => null,
        'insurance_cristalli_eur'    => null,
        'insurance_furto_eur'        => null,
    ];

    // Opzioni per select
    public array $fuelOptions = [
        'petrol' => 'Benzina', 'diesel' => 'Diesel', 'hybrid' => 'Ibrida',
        'electric' => 'Elettrica', 'lpg' => 'GPL', 'cng' => 'Metano',
    ];

    // Opzioni per select
    public array $transmissionOptions = [
        'manual' => 'Manuale', 'automatic' => 'Automatico',
    ];

    /**
     * Opzioni per la select Tipologia veicolo CARGOS (code => label).
     *
     * @var array<string,string>
     */
    public array $cargosVehicleTypeOptions = [];

    // Evita salvataggi multipli
    public bool $saving = false;

    /**
     * Mount the component.
     * @param Vehicle|null $vehicle
     * @return void
     */
    public function mount(?Vehicle $vehicle = null): void
    {
        // ✅ Se l’argomento non è un model valido, resta null
        $this->vehicle = ($vehicle && $vehicle->exists) ? $vehicle : null;
        // Carica master data CARGOS (solo attivi) per la select UI
        $this->cargosVehicleTypeOptions = $this->loadCargosVehicleTypes();

        if ($this->isEdit()) {
            $this->authorize('update', $this->vehicle);

            $this->form = [
                'admin_organization_id'      => $this->vehicle->admin_organization_id,
                'vin'                        => $this->vehicle->vin,
                'plate'                      => $this->vehicle->plate,
                'make'                       => $this->vehicle->make,
                'model'                      => $this->vehicle->model,
                'year'                       => $this->vehicle->year,
                'color'                      => $this->vehicle->color,
                'fuel_type'                  => $this->safeEnum($this->vehicle->fuel_type, array_keys($this->fuelOptions), 'petrol'),
                'transmission'               => $this->safeEnum($this->vehicle->transmission, array_keys($this->transmissionOptions), 'manual'),
                'seats'                      => $this->vehicle->seats,
                'segment'                    => $this->vehicle->segment,
                'cargos_vehicle_type_code' => $this->vehicle->cargos_vehicle_type_code,
                'mileage_current'            => (int) ($this->vehicle->mileage_current ?? 0),
                'default_pickup_location_id' => $this->vehicle->default_pickup_location_id,
                'is_active'                  => (int) ($this->vehicle->is_active ?? 1),
                'notes'                      => $this->vehicle->notes,

                // 👇 Prefill (da _cents → _eur)
                'lt_rental_monthly_eur'      => $this->vehicle->lt_rental_monthly_cents   !== null ? $this->vehicle->lt_rental_monthly_cents   / 100 : null,
                'insurance_kasko_eur'        => $this->vehicle->insurance_kasko_cents     !== null ? $this->vehicle->insurance_kasko_cents     / 100 : null,
                'insurance_rca_eur'          => $this->vehicle->insurance_rca_cents       !== null ? $this->vehicle->insurance_rca_cents       / 100 : null,
                'insurance_cristalli_eur'    => $this->vehicle->insurance_cristalli_cents !== null ? $this->vehicle->insurance_cristalli_cents / 100 : null,
                'insurance_furto_eur'        => $this->vehicle->insurance_furto_cents     !== null ? $this->vehicle->insurance_furto_cents     / 100 : null,
            ];
        } else {
            $this->authorize('create', Vehicle::class);

            // Prepara valori backend-only (non visualizzati)
            $this->form['is_active']       = 1;
            $this->form['fuel_type']       = 'petrol';
            $this->form['transmission']    = 'manual';
            $this->form['mileage_current'] = 0;

            // (i 5 campi economici restano null in create)
        }
    }

    /**
     * Regole di validazione
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $plateRule = Rule::unique('vehicles', 'plate');
        if ($this->isEdit()) {
            $plateRule = $plateRule->ignore($this->vehicle->id);
        }

        return [
            'form.vin'             => ['nullable','string','max:17'],
            'form.plate'           => ['required','string','max:16', $plateRule],
            'form.make'            => ['required','string','max:64'],
            'form.model'           => ['required','string','max:64'],
            'form.year'            => ['nullable','integer','between:1900,2100'],
            'form.color'           => ['nullable','string','max:32'],
            'form.fuel_type'       => ['required','in:petrol,diesel,hybrid,electric,lpg,cng'],
            'form.transmission'    => ['required','in:manual,automatic'],
            'form.seats'           => ['nullable','integer','min:1','max:99'],
            'form.segment'         => ['nullable','string','max:32'],
            'form.cargos_vehicle_type_code' => [
                'required',
                'string',
                'max:32',
                Rule::exists('cargos_vehicle_types', 'code')->where(function ($q) {
                    /**
                     * Valida che il codice selezionato esista tra le tipologie CARGOS attive.
                     * Così non dipendiamo dall’array opzioni (che è UI) ma dalla sorgente dati (DB).
                     */
                    $q->where('is_active', 1);
                }),
            ],
            'form.mileage_current' => ['required','integer','min:0'],
            'form.notes'           => ['nullable','string'],

            // 👇 Aggiunte economiche (UI in euro)
            'form.lt_rental_monthly_eur'   => ['nullable','numeric','min:0'],
            'form.insurance_kasko_eur'     => ['nullable','numeric','min:0'],
            'form.insurance_rca_eur'       => ['nullable','numeric','min:0'],
            'form.insurance_cristalli_eur' => ['nullable','numeric','min:0'],
            'form.insurance_furto_eur'     => ['nullable','numeric','min:0'],
            // i 3 campi “forzati” non si validano qui
        ];
    }

    /**
     * Normalizza la targa in uppercase senza spazi
     * @param string|null $value
     */
    public function updatedFormPlate(?string $value): void
    {
        $this->form['plate'] = Str::upper(trim((string) $value));
    }

    /**
     * Salva il veicolo (crea o aggiorna)
     * @return void
     */
    public function save(): void
    {
        if ($this->saving) return;
        $this->saving = true;

        // Scegli la policy in base alla modalità
        $this->isEdit()
            ? $this->authorize('update', $this->vehicle)
            : $this->authorize('create', Vehicle::class);

        $data = $this->validate()['form'];

        // Normalizzazioni coerenti con migration
        $data['plate']           = Str::upper(trim($data['plate']));
        $data['vin']             = $data['vin'] ? Str::upper(trim($data['vin'])) : null;
        $data['year']            = $data['year'] !== null ? (int) $data['year'] : null;
        $data['seats']           = $data['seats'] !== null ? (int) $data['seats'] : null;
        $data['mileage_current'] = (int) ($data['mileage_current'] ?? 0);
        $data['fuel_type']       = $this->safeEnum($data['fuel_type'], array_keys($this->fuelOptions), 'petrol');
        $data['transmission']    = $this->safeEnum($data['transmission'], array_keys($this->transmissionOptions), 'manual');

        // Converte "euro" → "cents" (accetta anche virgola come separatore decimale)
        $toCents = function ($value): ?int {
            if ($value === null || $value === '') return null;
            $norm = str_replace([',', ' '], ['.', ''], (string) $value);
            return (int) round(((float) $norm) * 100);
        };

        // Costruisco il payload finale per il Model (rimuovo *_eur, aggiungo *_cents)
        $save = $data;

        unset(
            $save['lt_rental_monthly_eur'],
            $save['insurance_kasko_eur'],
            $save['insurance_rca_eur'],
            $save['insurance_cristalli_eur'],
            $save['insurance_furto_eur'],
        );

        $save['lt_rental_monthly_cents']   = $toCents($data['lt_rental_monthly_eur']   ?? null);
        $save['insurance_kasko_cents']     = $toCents($data['insurance_kasko_eur']     ?? null);
        $save['insurance_rca_cents']       = $toCents($data['insurance_rca_eur']       ?? null);
        $save['insurance_cristalli_cents'] = $toCents($data['insurance_cristalli_eur'] ?? null);
        $save['insurance_furto_cents']     = $toCents($data['insurance_furto_eur']     ?? null);

        /**
         * Forzature backend:
         * - admin_organization_id: sempre coerente con l’admin loggato (come da tua logica attuale)
         * - is_active: sempre 1 (come da tua logica attuale)
         * - default_pickup_location_id: SOLO in CREATE (mai in EDIT)
         */
        $adminOrgId = $this->adminOrgId();
        if (!$adminOrgId) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Organizzazione admin non configurata.']);
            $this->saving = false;
            return;
        }

        $save['admin_organization_id'] = $adminOrgId;
        $save['is_active']             = 1;

        // ✅ SOLO CREATE: assegna la prima sede dell’organizzazione admin
        if (!$this->isEdit()) {
            $firstLocId = $this->firstAdminLocationId($adminOrgId);

            if (!$firstLocId) {
                $this->dispatch('toast', ['type' => 'error', 'message' => 'Sede non configurata per questa organizzazione.']);
                $this->saving = false;
                return;
            }

            $save['default_pickup_location_id'] = $firstLocId;
        }

        if ($this->isEdit()) {
            // ✅ In EDIT non tocchiamo default_pickup_location_id
            $this->vehicle->update($save);
            $vehicle = $this->vehicle->fresh();
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Veicolo aggiornato.']);
        } else {
            // ✅ CREATE: servono org admin e prima sede dell’org
            $adminOrgId = $this->adminOrgId();
            if (!$adminOrgId) {
                $this->dispatch('toast', ['type' => 'error', 'message' => 'Organizzazione admin non configurata.']);
                $this->saving = false;
                return;
            }

            $firstLocId = $this->firstAdminLocationId($adminOrgId);
            if (!$firstLocId) {
                $this->dispatch('toast', ['type' => 'error', 'message' => 'Sede non configurata per questa organizzazione.']);
                $this->saving = false;
                return;
            }
            $save['admin_organization_id']      = $adminOrgId;
            $save['default_pickup_location_id'] = $firstLocId;
            $save['is_active']                  = 1;

            $vehicle = Vehicle::create($save);
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Veicolo creato.']);
        }

        $this->redirectRoute('vehicles.show', ['vehicle' => $vehicle], navigate: true);
    }

    /**
     * Restituisce l’ID della prima sede dell’organizzazione admin, o null
     * @param int|null $orgId
     * @return int|null
     */
    private function firstAdminLocationId(?int $orgId): ?int
    {
        if (!$orgId) return null;

        /**
         * La migration conferma che locations ha organization_id,
         * quindi filtriamo SEMPRE: evita assegnazioni errate “cross-org”.
         */
        return Location::query()
            ->where('organization_id', $orgId)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Determina se siamo in modalità EDIT
     * @return bool
     */
    private function isEdit(): bool
    {
        return $this->vehicle instanceof Vehicle && $this->vehicle->exists;
    }

    /**
     * Restituisce un valore enum sicuro
     * @param mixed $value
     * @param array $allowed
     * @param mixed $fallback
     * @return mixed
     */
    private function safeEnum($value, array $allowed, $fallback)
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * Restituisce l’ID dell’organizzazione admin dell’utente, o null
     * @return int|null
     */
    private function adminOrgId(): ?int
    {
        $org = Auth::user()?->organization;
        if (!$org) return null;
        $isAdmin = method_exists($org, 'isAdmin') ? $org->isAdmin() : ($org->type === 'admin');
        return $isAdmin ? $org->id : null;
    }
    
    /**
     * Carica le tipologie veicolo CARGOS attive (code => label).
     *
     * @return array<string,string>
     */
    private function loadCargosVehicleTypes(): array
    {
        return CargosVehicleType::query()
            ->active()
            ->orderBy('label')
            ->pluck('label', 'code')
            ->toArray();
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.vehicles.form', [
            'isEdit'       => $this->isEdit(),
            'fuelOptions'  => $this->fuelOptions,
            'transOptions' => $this->transmissionOptions,
            'cargosVehicleTypeOptions' => $this->cargosVehicleTypeOptions,
        ]);
    }
}
