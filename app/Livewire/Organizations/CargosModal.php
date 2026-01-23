<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Modale Livewire: Cargos per Organization di tipo "renter".
 *
 * Sicurezza:
 * - NON carichiamo i valori reali nello state all'apertura (per non spedirli nel payload Livewire).
 * - Per visualizzarli, richiediamo conferma password admin e mostriamo i valori per pochi secondi.
 * - In salvataggio, se i campi sono vuoti, manteniamo i valori esistenti.
 */
class CargosModal extends Component
{
    public bool $open = false;
    public ?int $organizationId = null;

    /**
     * Indicatori non sensibili: ci dicono se i valori risultano impostati (senza leggere i valori).
     */
    public bool $hasCargosPassword = false;
    public bool $hasCargosPuk = false;
    public bool $hasCargosUserCode = false;
    public bool $hasCargosAgencyId = false;

    /**
     * Form state: valori da salvare.
     * - Vuoto => non cambia il valore già presente.
     */
    public array $state = [
        'codice_utente_cargos' => null,
        'agenzia_id_cargos'    => null,
        'cargos_password'      => null,
        'cargos_puk'           => null,
    ];

    /**
     * Conferma password admin per rivelare i cargos.
     */
    public string $confirmPassword = '';

    /**
     * Valori rivelati (solo dopo conferma). NON vengono salvati qui.
     */
    public ?string $revealedCargosUserCode = null;
    public ?string $revealedCargosAgencyId = null;
    public ?string $revealedCargosPassword = null;
    public ?string $revealedCargosPuk = null;

    protected $listeners = [
        'open-org-cargos' => 'openModal',
    ];

    public function mount(): void
    {
        if (!Gate::allows('manage.renters')) {
            abort(403);
        }
    }

    protected function rules(): array
    {
        return [
            'state.codice_utente_cargos' => ['nullable', 'string', 'max:80'],
            'state.agenzia_id_cargos'    => ['nullable', 'string', 'max:32'],

            'state.cargos_password'      => ['nullable', 'string', 'max:255'],
            'state.cargos_puk'           => ['nullable', 'string', 'max:255'],

            'confirmPassword' => ['nullable', 'string'],
        ];
    }

    protected array $messages = [
        'state.codice_utente_cargos.max' => 'Il codice utente Cargos può contenere al massimo :max caratteri.',
        'state.agenzia_id_cargos.max'    => "L'agenzia ID Cargos può contenere al massimo :max caratteri.",

        'state.cargos_password.max'      => 'La password Cargos può contenere al massimo :max caratteri.',
        'state.cargos_puk.max'           => 'Il PUK Cargos può contenere al massimo :max caratteri.',
    ];

    public function openModal(int $organizationId): void
    {
        $this->resetValidation();

        $this->organizationId = $organizationId;

        // Pulizia state e reveal per sicurezza.
        $this->state = [
            'codice_utente_cargos' => null,
            'agenzia_id_cargos'    => null,
            'cargos_password'      => null,
            'cargos_puk'           => null,
        ];

        $this->confirmPassword = '';
        $this->hideReveals();

        // Carichiamo solo l'organizzazione renter attiva (no trashed).
        $org = Organization::query()
            ->where('type', 'renter')
            ->whereKey($organizationId)
            ->firstOrFail();

        /**
         * Indicatori senza “pre-fill”.
         * - Se i campi sono castati encrypted, usare getRawOriginal è perfetto (non decripta).
         * - Se NON sono encrypted, è comunque ok: qui ci serve solo sapere “è valorizzato?”
         */
        $this->hasCargosPassword  = !empty($org->getRawOriginal('cargos_password'));
        $this->hasCargosPuk       = !empty($org->getRawOriginal('cargos_puk'));
        $this->hasCargosUserCode  = !empty($org->getRawOriginal('codice_utente_cargos'));
        $this->hasCargosAgencyId  = !empty($org->getRawOriginal('agenzia_id_cargos'));

        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->organizationId = null;

        $this->hasCargosPassword = false;
        $this->hasCargosPuk = false;
        $this->hasCargosUserCode = false;
        $this->hasCargosAgencyId = false;

        $this->state = [
            'codice_utente_cargos' => null,
            'agenzia_id_cargos'    => null,
            'cargos_password'      => null,
            'cargos_puk'           => null,
        ];

        $this->confirmPassword = '';
        $this->hideReveals();

        $this->resetValidation();
    }

    public function hideReveals(): void
    {
        $this->revealedCargosUserCode = null;
        $this->revealedCargosAgencyId = null;
        $this->revealedCargosPassword = null;
        $this->revealedCargosPuk = null;

        $this->confirmPassword = '';
    }

    /**
     * Reveal: richiede password admin e mostra temporaneamente i valori.
     * - $field: 'user_code' | 'agency_id' | 'password' | 'puk'
     */
    public function reveal(string $field): void
    {
        $this->resetErrorBag('confirmPassword');

        $user = Auth::user();

        // Guardia: solo admin
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole('admin')) {
            abort(403);
        }

        if (empty($this->organizationId)) {
            return;
        }

        if (trim($this->confirmPassword) === '') {
            $this->addError('confirmPassword', 'Inserisci la password admin per visualizzare i dati.');
            return;
        }

        if (!Hash::check($this->confirmPassword, (string) $user->password)) {
            $this->addError('confirmPassword', 'Password admin non valida.');
            return;
        }

        $org = Organization::query()
            ->where('type', 'renter')
            ->whereKey($this->organizationId)
            ->firstOrFail();

        if ($field === 'user_code') {
            $this->revealedCargosUserCode = $org->codice_utente_cargos;
        }

        if ($field === 'agency_id') {
            $this->revealedCargosAgencyId = $org->agenzia_id_cargos;
        }

        if ($field === 'password') {
            $this->revealedCargosPassword = $org->cargos_password;
        }

        if ($field === 'puk') {
            $this->revealedCargosPuk = $org->cargos_puk;
        }

        // igiene: svuota password admin
        $this->confirmPassword = '';

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'Dati Cargos visualizzati temporaneamente.',
            'duration' => 2500,
        ]);
    }

    public function save(): void
    {
        $this->validate();

        if (empty($this->organizationId)) {
            return;
        }

        try {
            $org = Organization::query()
                ->where('type', 'renter')
                ->whereKey($this->organizationId)
                ->firstOrFail();

            $updates = [];

            // ✅ Nuovi campi
            if (!empty($this->state['codice_utente_cargos'])) {
                $updates['codice_utente_cargos'] = $this->state['codice_utente_cargos'];
            }

            if (!empty($this->state['agenzia_id_cargos'])) {
                $updates['agenzia_id_cargos'] = $this->state['agenzia_id_cargos'];
            }

            // ✅ Esistenti
            if (!empty($this->state['cargos_password'])) {
                $updates['cargos_password'] = $this->state['cargos_password'];
            }

            if (!empty($this->state['cargos_puk'])) {
                $updates['cargos_puk'] = $this->state['cargos_puk'];
            }

            if (empty($updates)) {
                $this->dispatch('toast', [
                    'type' => 'warning',
                    'message' => 'Nessuna modifica da salvare.',
                    'duration' => 3000,
                ]);
                return;
            }

            $org->update($updates);

            // aggiorna indicatori (senza leggere valori)
            $this->hasCargosPassword = !empty($org->getRawOriginal('cargos_password'));
            $this->hasCargosPuk      = !empty($org->getRawOriginal('cargos_puk'));
            $this->hasCargosUserCode = !empty($org->getRawOriginal('codice_utente_cargos'));
            $this->hasCargosAgencyId = !empty($org->getRawOriginal('agenzia_id_cargos'));

            // pulizia state inserito + reveal
            $this->state = [
                'codice_utente_cargos' => null,
                'agenzia_id_cargos'    => null,
                'cargos_password'      => null,
                'cargos_puk'           => null,
            ];
            $this->hideReveals();

            $this->dispatch('organizations:updated');

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Dati Cargos salvati correttamente.',
                'duration' => 3000,
            ]);

        } catch (QueryException $e) {
            Log::error('CargosModal save QueryException', [
                'organization_id' => $this->organizationId,
                'message' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Errore durante il salvataggio dei dati Cargos. Riprova.',
                'duration' => 4500,
            ]);

        } catch (\Throwable $e) {
            Log::error('CargosModal save Throwable', [
                'organization_id' => $this->organizationId,
                'message' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Errore durante il salvataggio dei dati Cargos. Riprova.',
                'duration' => 4500,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.organizations.cargos-modal');
    }
}
