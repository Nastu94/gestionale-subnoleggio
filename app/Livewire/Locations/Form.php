<?php

namespace App\Livewire\Locations;

use App\Models\Location;
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

        if ($this->location) {
            // EDIT
            $this->authorize('update', $this->location);
            $this->location->fill($data)->save();

            $this->dispatch('toast', type:'success', message:'Sede aggiornata correttamente.');
            $this->dispatch('navigate', url: route('locations.show', $this->location));
            return;
        }

        // CREATE
        $this->authorize('create', Location::class);
        $loc = new Location();
        $loc->fill($data);
        $loc->organization_id = (int) Auth::user()->organization_id; // abbinata a chi crea
        $loc->save();

        $this->dispatch('toast', type:'success', message:'Sede creata correttamente.');
        $this->dispatch('navigate', url: route('locations.show', $loc));
    }

    public function render()
    {
        return view('livewire.locations.form', [
            'isEdit' => (bool) $this->location,
        ]);
    }
}
