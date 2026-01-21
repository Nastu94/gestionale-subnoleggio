<?php

namespace App\Livewire\Locations;

use App\Models\Location;
use App\Models\CargosLuogo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Livewire: Sedi ▸ Form (Create + Edit)
 *
 * - Se $location è null ⇒ CREATE; altrimenti ⇒ EDIT.
 * - Autorizzazioni: create/update via Policy.
 * - Toast-only: emit 'toast' e poi 'navigate' verso la show.
 */
class Form extends Component
{
    use AuthorizesRequests;

    /** Modello in modifica (null in creazione) */
    public ?Location $location = null;

    // Campi esistenti (nessun rename)
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


    public function mount(?Location $location = null): void
    {
        $this->location = $location;

        // Autorizza in base alla modalità
        if ($this->location) {
            $this->authorize('update', $this->location);
            // Precarica i campi dal modello
            $this->fill($this->location->only([
                'name','address_line','city','province','postal_code','country_code','notes',
            ]));
            $this->provinceSearch = $this->province;
            $this->citySearch     = $this->city;

            /**
             * Se presente, carichiamo anche il codice CARGOS.
             * (ma NON lo usiamo come condizione per mostrare la città)
             */
            if ($this->location?->police_place_code) {
                $this->police_place_code = $this->location->police_place_code;
            }
        } else {
            $this->authorize('create', Location::class);
        }
    }

    /** Regole di validazione allineate alle migration */
    protected function rules(): array
    {
        return [
            'name'         => ['required','string','min:2','max:191'],
            'address_line' => ['nullable','string','max:191'],
            'city'         => ['nullable','string','max:128'],
            'province'     => ['nullable','string','max:64'],
            'postal_code'  => ['nullable','string','max:16'],
            'country_code' => ['nullable','string','size:2'],
            'notes'        => ['nullable','string'],
        ];
    }

    /** Salva (crea o aggiorna) + toast + navigate verso la show */
    public function save(): void
    {
        $data = $this->validate();

        if (! $this->police_place_code) {
            $this->dispatch(
                'toast',
                type: 'error',
                message: 'Seleziona un comune valido dall’elenco.'
            );
            return;
        }

        if ($this->location) {
            // EDIT
            $this->authorize('update', $this->location);
            $this->location->fill($data)->save();

            // ✅ Persistenza esplicita codice CARGOS
            $this->location->police_place_code = $this->police_place_code;

            $this->location->save();


            $this->dispatch('toast', type:'success', message:'Sede aggiornata correttamente.');
            $this->dispatch('navigate', url: route('locations.show', $this->location));
            return;
        }

        // CREATE
        $this->authorize('create', Location::class);
        $loc = new Location();
        $loc->fill($data);
        $loc->organization_id = (int) Auth::user()->organization_id; // abbinata a chi crea

        // ✅ Persistenza esplicita codice CARGOS
        $loc->police_place_code = $this->police_place_code;
        $loc->save();

        $this->dispatch('toast', type:'success', message:'Sede creata correttamente.');
        $this->dispatch('navigate', url: route('locations.show', $loc));
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
        return view('livewire.locations.form', [
            'isEdit' => (bool) $this->location,
        ]);
    }
}
