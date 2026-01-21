<?php

namespace App\Livewire\Locations;

use App\Models\Location;
use App\Models\CargosLuogo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Livewire: Sedi ▸ Create
 *
 * - Consente ad admin e renter di creare una sede "abbinata" alla propria organization.
 * - Validazione secondo i vincoli DB (no rename campi).
 * - Notifiche via toast; dopo il salvataggio, redirect alla show.
 */
class Create extends Component
{
    use AuthorizesRequests;

    // Campi esistenti sul Model/Migration (nessun rename)
    public ?string $name         = null;
    public ?string $address_line = null;
    public ?string $city         = null;
    public ?string $province     = null;
    public ?string $postal_code  = null;
    public ?string $country_code = null;
    public ?string $notes        = null;
    public ?string $provinceSearch = null;
    public array $provinceResults = [];
    public ?string $citySearch = null;
    public array $cityResults = [];
    public ?int $police_place_code = null;



    public function mount(): void
    {
        // Policy: LocationPolicy@create deve consentire a admin e renter
        $this->authorize('create', Location::class);
    }

    /** Regole di validazione: aderenti ai vincoli delle migration */
    protected function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'min:2', 'max:191'],
            'address_line' => ['nullable', 'string', 'max:191'],
            'city'         => ['nullable', 'string', 'max:128'],
            'province'     => ['nullable', 'string', 'max:64'],
            'postal_code'  => ['nullable', 'string', 'max:16'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'notes'        => ['nullable', 'string'],
        ];
    }

    /** Salva e reindirizza alla show, mantenendo il paradigma toast-only. */
    public function save(): void
    {
        $this->authorize('create', Location::class);

        $data = $this->validate();

        // 🔒 Guardia di dominio: il codice DEVE esserci
        if (! $this->police_place_code) {
            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Seleziona un comune valido dall’elenco.'
            );
            return;
        }

        // organization_id = del chiamante (admin o renter)
        $orgId = (int) Auth::user()->organization_id;

        $location = new Location();
        $location->fill($data);

        // ✅ Persistenza esplicita codice CARGOS
        $location->police_place_code = $this->police_place_code;
        $location->organization_id = $orgId;

        $location->save();

        // Toast di successo
        $this->dispatch('toast', type: 'success', message: 'Sede creata correttamente.');

        // Redirect alla show
        $this->dispatch('navigate', url: route('locations.show', $location));
    }

    /**
     * Aggiorna i risultati del campo provincia in base alla ricerca
     */
    public function updatedProvinceSearch(): void
    {
        $term = strtoupper(trim($this->provinceSearch));

        if (strlen($term) < 1) {
            $this->provinceResults = [];
            return;
        }

        $this->provinceResults = CargosLuogo::query()
            ->where('is_active', true)
            ->where('is_italian', true)
            ->whereNotNull('province_code')
            ->where('province_code', 'like', "{$term}%")
            ->distinct()
            ->orderBy('province_code')
            ->pluck('province_code')
            ->toArray();
    }

    /**
     * Seleziona una provincia dai risultati
     */
    public function selectProvince(string $province): void
    {
        $this->province = $province;
        $this->provinceSearch = $province;
        $this->provinceResults = [];
        $this->updatedProvince();
    }
    
    /**
     * Aggiorna i risultati del campo comune in base alla ricerca
     */
    public function updatedCitySearch(): void
    {
        if (! $this->province) {
            $this->cityResults = [];
            return;
        }

        $term = trim($this->citySearch);

        if (strlen($term) < 2) {
            $this->cityResults = [];
            return;
        }

        $this->cityResults = CargosLuogo::query()
            ->where('is_active', true)
            ->where('is_italian', true)
            ->where('province_code', $this->province)
            ->where('name', 'like', "%{$term}%")
            ->orderBy('name')
            ->get(['code','name'])
            ->toArray();
    }

    /**
     * Seleziona un comune dai risultati
     */
    public function selectCity(int $code, string $name): void
    {
        $this->city = $name;
        $this->citySearch = $name;
        $this->police_place_code = $code;

        $this->cityResults = [];
    }

    /**
    * Resetta i campi collegati quando cambia la provincia
    */
    public function updatedProvince(): void
    {
        // Reset città e codice CARGOS
        $this->city = null;
        $this->citySearch = null;
        $this->police_place_code = null;
        $this->cityResults = [];
    }

    public function render()
    {
        return view('livewire.locations.create');
    }
}
