<?php

namespace App\Livewire\Profile;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Form profilo: Dati anagrafici dell'Organizzazione Admin.
 *
 * - Visibile/Usabile solo dagli utenti con ruolo admin.
 * - Aggiorna l'organization collegata all'utente (type = admin).
 * - NON include la licenza (come richiesto).
 */
class UpdateAdminAnagraphicForm extends Component
{
    /**
     * Stato form.
     *
     * @var array<string, mixed>
     */
    public array $state = [
        'legal_name'   => null,
        'vat'          => null,
        'address_line' => null,
        'city'         => null,
        'province'     => null,
        'postal_code'  => null,
        'country_code' => null,
        'phone'        => null,
        'email'        => null,
    ];

    /**
     * Organization admin corrente.
     */
    public ?Organization $organization = null;

    /**
     * Mount: verifica ruolo admin e carica l'organization admin.
     */
    public function mount(): void
    {
        $user = Auth::user();

        // Se non è admin, la sezione non deve essere accessibile.
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403);
        }

        // Carica l'organizzazione collegata all'admin (type=admin).
        $this->organization = Organization::query()
            ->whereKey($user->organization_id)
            ->where('type', 'admin')
            ->firstOrFail();

        // Precompila i campi.
        $this->state = [
            'legal_name'   => $this->organization->legal_name,
            'vat'          => $this->organization->vat,
            'address_line' => $this->organization->address_line,
            'city'         => $this->organization->city,
            'province'     => $this->organization->province,
            'postal_code'  => $this->organization->postal_code,
            'country_code' => $this->organization->country_code,
            'phone'        => $this->organization->phone,
            'email'        => $this->organization->email,
        ];
    }

    /**
     * Regole validazione.
     *
     * - P.IVA unica tra tutte le organizations (ignore sull'attuale org admin).
     *
     * @return array<string, mixed>
     */
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
        ];
    }

    /**
     * Messaggi di validazione personalizzati.
     *
     * @var array<string, string>
     */
    protected array $messages = [
        'state.legal_name.max' => 'La ragione sociale può contenere al massimo :max caratteri.',
        'state.vat.max'        => 'La Partita IVA può contenere al massimo :max caratteri.',
        'state.vat.unique'     => 'Esiste già un’organizzazione con questa Partita IVA.',
        'state.email.email'    => 'Inserisci un indirizzo email valido.',
        'state.email.max'      => 'L’email può contenere al massimo :max caratteri.',
        'state.phone.max'      => 'Il telefono può contenere al massimo :max caratteri.',
        'state.address_line.max' => 'L’indirizzo può contenere al massimo :max caratteri.',
        'state.city.max'         => 'La città può contenere al massimo :max caratteri.',
        'state.province.max'     => 'La provincia può contenere al massimo :max caratteri.',
        'state.postal_code.max'  => 'Il CAP può contenere al massimo :max caratteri.',
        'state.country_code.max' => 'Il codice paese può contenere al massimo :max caratteri.',
    ];

    /**
     * Salva i dati anagrafici dell'organizzazione admin.
     */
    public function updateAdminAnagraphic(): void
    {
        $this->validate();

        // Sicurezza: organization deve essere caricata.
        if (! $this->organization) {
            abort(403);
        }

        $this->organization->update([
            'legal_name'   => $this->state['legal_name'],
            'vat'          => $this->state['vat'],
            'address_line' => $this->state['address_line'],
            'city'         => $this->state['city'],
            'province'     => $this->state['province'],
            'postal_code'  => $this->state['postal_code'],
            'country_code' => $this->state['country_code'],
            'phone'        => $this->state['phone'],
            'email'        => $this->state['email'],
        ]);

        // Stile Jetstream: mostra "Salvato."
        $this->dispatch('saved');
    }

    public function render()
    {
        return view('profile.update-admin-anagraphic-form');
    }
}
