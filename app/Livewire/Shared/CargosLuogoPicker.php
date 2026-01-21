<?php

namespace App\Livewire\Shared;

use App\Models\CargosLuogo;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Picker riutilizzabile per "luoghi" CARGOS (nazione/provincia/comune).
 *
 * Regole:
 * - Se scegli una NAZIONE estera -> value = code nazione
 * - Se scegli ITALIA -> value deve essere il code del COMUNE (provincia+comune)
 * - In tabella CargosLuogo:
 *   - le NAZIONI hanno tipicamente province_code = 'ES'
 *   - per capire se è Italia/Italia-like si usa is_italian (country_code può essere NULL)
 */
class CargosLuogoPicker extends Component
{
    /**
     * Valore finale da salvare nel parent (place_code):
     * - comune (Italia) oppure nazione (estero)
     */
    #[Modelable]
    public ?int $value = null;

    /** Titolo mostrato nel box (parametro Blade: title="...") */
    public string $title = 'Luogo';

    /** Hint sotto al titolo (parametro Blade: hint="...") */
    public ?string $hint = null;
    
    public string $mode = 'full'; // full | country-only

    /**
     * Stato UI interno
     */
    public array $state = [
        'country_search'  => null,
        'country_code'    => null, // può essere null
        'country_cargos'  => null,
        'is_italian'      => false,

        'province_search' => null,
        'province'        => null,

        'city_search'     => null,
        'city'            => null,
    ];

    public array $countryResults = [];
    public array $provinceResults = [];
    public array $cityResults = [];

    public function mount(?int $value = null, string $title = 'Luogo', ?string $hint = null, string $mode = 'full'): void
    {
        // Parametri UI
        $this->title = $title;
        $this->hint  = $hint;
        $this->mode  = $mode;
        // Valore iniziale: se Livewire lo passa come argomento ok, altrimenti prendi quello già settato
        $this->value = $value ?? $this->value;

        $this->bootstrapFromValue();
    }

    /**
     * In alcuni casi il valore arriva dopo mount (binding da parent).
     * Questa guardia rende il prefill "a prova di sincronizzazione".
     */
    public function hydrate(): void
    {
        if ($this->value && empty($this->state['city_search']) && empty($this->state['country_search'])) {
            $this->bootstrapFromValue();
        }
    }

    public function updatedValue($v): void
    {
        $this->value = $v ? (int) $v : null;
        $this->bootstrapFromValue();
    }

    private function resetResults(): void
    {
        $this->countryResults = [];
        $this->provinceResults = [];
        $this->cityResults = [];
    }

    /**
     * PRECARICO dal valore salvato:
     * - Se value è COMUNE -> precompila Italia + provincia + comune
     * - Se value è NAZIONE -> precompila solo nazione
     */
    private function bootstrapFromValue(): void
    {
        $this->resetResults();

        if (! $this->value) {
            $this->state = [
                'country_search'  => null,
                'country_code'    => null,
                'country_cargos'  => null,
                'is_italian'      => false,
                'province_search' => null,
                'province'        => null,
                'city_search'     => null,
                'city'            => null,
            ];
            return;
        }

        $luogo = CargosLuogo::find($this->value);
        if (! $luogo) {
            return;
        }

        // NAZIONE (ES)
        if ($luogo->province_code === 'ES') {
            $isItalian = (bool) $luogo->is_italian;

            $this->state['country_search'] = $luogo->name;
            $this->state['country_code']   = $luogo->country_code; // può essere null
            $this->state['country_cargos'] = $luogo->code;
            $this->state['is_italian']     = $isItalian;

            // Se Italia come NAZIONE (edge), non puoi dedurre provincia/comune
            $this->state['province_search'] = null;
            $this->state['province'] = null;
            $this->state['city_search'] = null;
            $this->state['city'] = null;

            return;
        }

        // COMUNE (Italia)
        $this->state['country_search'] = 'Italia';
        $this->state['country_code']   = 'IT';
        $this->state['country_cargos'] = null;
        $this->state['is_italian']     = true;

        $this->state['province']        = $luogo->province_code;
        $this->state['province_search'] = $luogo->province_code;

        $this->state['city']        = $luogo->name;
        $this->state['city_search'] = $luogo->name;
    }

    // =========================
    // NAZIONE
    // =========================
    public function updatedStateCountrySearch(): void
    {
        $t = strtoupper(trim($this->state['country_search'] ?? ''));
        if (strlen($t) < 2) {
            $this->countryResults = [];
            return;
        }

        $this->countryResults = CargosLuogo::query()
            ->where('province_code', 'ES')
            ->where('name', 'like', "%{$t}%")
            ->orderBy('name')
            ->get(['code', 'name', 'country_code', 'is_italian'])
            ->toArray();
    }

    public function selectCountry(int $code, string $name, ?string $iso, bool $isItalian): void
    {
        $this->state['country_search'] = $name;
        $this->state['country_code']   = $iso;
        $this->state['country_cargos'] = $code;
        $this->state['is_italian']     = (bool) $isItalian;

        // reset downstream
        $this->state['province_search'] = null;
        $this->state['province'] = null;
        $this->state['city_search'] = null;
        $this->state['city'] = null;

        $this->provinceResults = [];
        $this->cityResults = [];

        /**
         * ✅ Comportamento corretto:
         * - mode=country-only (Cittadinanza): salva SEMPRE il code, anche se Italia
         * - mode=full (Residenza / Luoghi): se Italia, forza scelta provincia/comune => value null
         */
        if ($this->mode === 'country-only') {
            $this->value = $code;                 // <-- qui la differenza
        } else {
            $this->value = $isItalian ? null : $code;
        }

        $this->countryResults = [];
    }

    // =========================
    // PROVINCE (solo Italia)
    // =========================
    public function updatedStateProvinceSearch(): void
    {
        if (!($this->state['is_italian'] ?? false)) {
            $this->provinceResults = [];
            return;
        }

        $t = strtoupper(trim($this->state['province_search'] ?? ''));
        if ($t === '') {
            $this->provinceResults = [];
            return;
        }

        $this->provinceResults = CargosLuogo::query()
            ->where('is_italian', true)
            ->whereNotNull('province_code')
            ->where('province_code', 'like', "{$t}%")
            ->distinct()
            ->orderBy('province_code')
            ->pluck('province_code')
            ->toArray();
    }

    public function selectProvince(string $prov): void
    {
        if (!($this->state['is_italian'] ?? false)) {
            return;
        }

        $this->state['province'] = $prov;
        $this->state['province_search'] = $prov;

        // reset città + value (codice finale arriva dal comune)
        $this->state['city'] = null;
        $this->state['city_search'] = null;
        $this->value = null;

        $this->provinceResults = [];
        $this->cityResults = [];
    }

    // =========================
    // COMUNI (solo Italia)
    // =========================
    public function updatedStateCitySearch(): void
    {
        if (!($this->state['is_italian'] ?? false)) {
            $this->cityResults = [];
            return;
        }

        if (!($this->state['province'] ?? null)) {
            $this->cityResults = [];
            return;
        }

        $t = trim($this->state['city_search'] ?? '');
        if (strlen($t) < 2) {
            $this->cityResults = [];
            return;
        }

        $this->cityResults = CargosLuogo::query()
            ->where('is_italian', true)
            ->where('province_code', $this->state['province'])
            ->where('name', 'like', "%{$t}%")
            ->orderBy('name')
            ->get(['code', 'name'])
            ->toArray();
    }

    public function selectCity(int $code, string $name): void
    {
        if (!($this->state['is_italian'] ?? false)) {
            return;
        }

        $this->state['city'] = $name;
        $this->state['city_search'] = $name;

        $this->value = $code;

        $this->cityResults = [];
    }

    public function render()
    {
        return view('livewire.shared.cargos-luogo-picker');
    }
}
