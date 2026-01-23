<?php

namespace App\Livewire\Profile;

use App\Models\Organization;
use App\Models\CargosLuogo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UpdateAdminAnagraphicForm extends Component
{
    public array $state = [
        'legal_name'        => null,
        'vat'               => null,
        'address_line'      => null,

        // ✅ questi non li compila l'utente: li deriviamo dal picker
        'city'              => null,
        'province'          => null,
        'country_code'      => null,

        'postal_code'       => null,
        'phone'             => null,
        'email'             => null,

        // ✅ CARGOS luogo (Comune IT oppure Nazione estera)
        'police_place_code' => null,
    ];

    public ?Organization $organization = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403);
        }

        $this->organization = Organization::query()
            ->whereKey($user->organization_id)
            ->where('type', 'admin')
            ->firstOrFail();

        $this->state = [
            'legal_name'        => $this->organization->legal_name,
            'vat'               => $this->organization->vat,
            'address_line'      => $this->organization->address_line,

            // valori esistenti (li sovrascriveremo in sync se serve)
            'city'              => $this->organization->city,
            'province'          => $this->organization->province,
            'country_code'      => $this->organization->country_code,

            'postal_code'       => $this->organization->postal_code,
            'phone'             => $this->organization->phone,
            'email'             => $this->organization->email,

            'police_place_code' => $this->organization->police_place_code
                ? (int) $this->organization->police_place_code
                : null,
        ];

        // ✅ allinea city/province/country_code al valore del picker già salvato
        $this->syncGeoFieldsFromPolicePlaceCode();
    }

    protected function rules(): array
    {
        return [
            'state.legal_name'   => ['nullable', 'string', 'max:191'],
            'state.vat'          => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('organizations', 'vat')->ignore($this->organization?->id),
            ],
            'state.address_line' => ['nullable', 'string', 'max:191'],
            'state.city'         => ['nullable', 'string', 'max:191'],
            'state.province'     => ['nullable', 'string', 'max:191'],
            'state.postal_code'  => ['nullable', 'string', 'max:32'],
            'state.country_code' => ['nullable', 'string', 'max:8'],
            'state.phone'        => ['nullable', 'string', 'max:64'],
            'state.email'        => ['nullable', 'email', 'max:191'],

            'state.police_place_code' => ['nullable', 'integer', 'exists:cargos_luoghi,code'],
        ];
    }

    protected array $messages = [
        'state.vat.unique' => 'Esiste già un’organizzazione con questa Partita IVA.',
        'state.email.email' => 'Inserisci un indirizzo email valido.',

        'state.police_place_code.integer' => 'Il codice luogo deve essere numerico.',
        'state.police_place_code.exists'  => 'Seleziona un luogo valido dal picker CARGOS.',
    ];

    /**
     * ✅ Hook Livewire: quando cambia il picker, riallinea i campi derivati.
     */
    public function updatedStatePolicePlaceCode($value): void
    {
        $this->state['police_place_code'] = $value ? (int) $value : null;
        $this->syncGeoFieldsFromPolicePlaceCode();
    }

    /**
     * ✅ Fonte unica: police_place_code (CargosLuogo).
     *
     * Regole:
     * - NULL => city/province/country_code = null
     * - Nazione estera (province_code = 'ES') => country_code = luogo.country_code (può essere null), city/province null
     * - Comune IT => country_code = 'IT', province = luogo.province_code, city = luogo.name
     *
     * Nota: sovrascriviamo SEMPRE (anche null), come richiesto.
     */
    private function syncGeoFieldsFromPolicePlaceCode(): void
    {
        $code = $this->state['police_place_code'] ?? null;
        $code = $code ? (int) $code : null;

        if (! $code) {
            $this->state['city'] = null;
            $this->state['province'] = null;
            $this->state['country_code'] = null;
            return;
        }

        $luogo = CargosLuogo::query()->find($code);
        if (! $luogo) {
            // verrà comunque bloccato dalla validation exists, ma intanto puliamo
            $this->state['city'] = null;
            $this->state['province'] = null;
            $this->state['country_code'] = null;
            return;
        }

        // NAZIONE (estero)
        if ($luogo->province_code === 'ES') {
            $this->state['country_code'] = $luogo->country_code ?: null;
            $this->state['province'] = null;
            $this->state['city'] = null;
            return;
        }

        // COMUNE ITALIA
        $this->state['country_code'] = 'IT';
        $this->state['province'] = $luogo->province_code ?: null;
        $this->state['city'] = $luogo->name ?: null;
    }

    public function updateAdminAnagraphic(): void
    {
        $this->validate();

        if (! $this->organization) {
            abort(403);
        }

        // ✅ ultima difesa: garantisce coerenza anche se l'hook non è scattato
        $this->syncGeoFieldsFromPolicePlaceCode();

        $this->organization->update([
            'legal_name'        => $this->state['legal_name'],
            'vat'               => $this->state['vat'],
            'address_line'      => $this->state['address_line'],

            // ✅ sovrascrivi SEMPRE (anche null)
            'city'              => $this->state['city'],
            'province'          => $this->state['province'],
            'country_code'      => $this->state['country_code'],

            'postal_code'       => $this->state['postal_code'],
            'phone'             => $this->state['phone'],
            'email'             => $this->state['email'],

            'police_place_code' => $this->state['police_place_code'],
        ]);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('profile.update-admin-anagraphic-form');
    }
}
