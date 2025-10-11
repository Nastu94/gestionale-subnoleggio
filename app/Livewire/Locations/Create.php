<?php

namespace App\Livewire\Locations;

use App\Models\Location;
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

        // organization_id = del chiamante (admin o renter)
        $orgId = (int) Auth::user()->organization_id;

        $location = new Location();
        $location->fill($data);
        $location->organization_id = $orgId;
        $location->save();

        // 1) Toast di successo
        $this->dispatch('toast', type: 'success', message: 'Sede creata correttamente.');

        // 2) Navigazione (rimandata di ~700ms dal listener Alpine in pagina)
        $this->dispatch('navigate', url: route('locations.show', $location));
    }

    public function render()
    {
        return view('livewire.locations.create');
    }
}
