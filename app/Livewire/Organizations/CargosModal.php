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
 * Modale Livewire: Cargos (password + PUK) per Organization di tipo "renter".
 *
 * Sicurezza:
 * - NON carichiamo i valori cifrati nello state all'apertura (per non spedirli nel payload Livewire).
 * - Per visualizzarli, richiediamo conferma password admin e mostriamo i valori per pochi secondi.
 * - In salvataggio, se i campi sono vuoti, manteniamo i valori esistenti.
 */
class CargosModal extends Component
{
    /**
     * Stato di apertura del modale.
     */
    public bool $open = false;

    /**
     * Organization selezionata.
     */
    public ?int $organizationId = null;

    /**
     * Indicatori non sensibili: ci dicono se i valori risultano impostati (senza decrypt).
     */
    public bool $hasCargosPassword = false;
    public bool $hasCargosPuk = false;

    /**
     * Form state: valori da salvare.
     * - Vuoto => non cambia il valore già presente.
     *
     * @var array<string, mixed>
     */
    public array $state = [
        'cargos_password' => null,
        'cargos_puk'      => null,
    ];

    /**
     * Conferma password admin per rivelare i cargos.
     */
    public string $confirmPassword = '';

    /**
     * Valori rivelati (solo dopo conferma). NON vengono salvati qui.
     */
    public ?string $revealedCargosPassword = null;
    public ?string $revealedCargosPuk = null;

    /**
     * Listener eventi Livewire/Browser.
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'open-org-cargos' => 'openModal',
    ];

    /**
     * Accesso: admin-only via Gate.
     */
    public function mount(): void
    {
        if (! Gate::allows('manage.renters')) {
            abort(403);
        }
    }

    /**
     * Regole validazione per salvataggio (non per reveal).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // Se presenti, devono essere stringhe. Non obbligatori.
            'state.cargos_password' => ['nullable', 'string', 'max:255'],
            'state.cargos_puk'      => ['nullable', 'string', 'max:255'],

            // Password admin richiesta solo quando si tenta il reveal (validazione manuale in reveal()).
            'confirmPassword' => ['nullable', 'string'],
        ];
    }

    /**
     * Messaggi personalizzati.
     *
     * @var array<string, string>
     */
    protected array $messages = [
        'state.cargos_password.max' => 'La password Cargos può contenere al massimo :max caratteri.',
        'state.cargos_puk.max'      => 'Il PUK Cargos può contenere al massimo :max caratteri.',
    ];

    /**
     * Apre il modale.
     * NB: riceviamo solo l'id (nessun segreto nel payload).
     */
    public function openModal(int $organizationId): void
    {
        $this->resetValidation();

        $this->organizationId = $organizationId;

        // Pulizia campi e reveal per sicurezza.
        $this->state['cargos_password'] = null;
        $this->state['cargos_puk'] = null;
        $this->confirmPassword = '';
        $this->revealedCargosPassword = null;
        $this->revealedCargosPuk = null;

        // Carichiamo solo l'organizzazione renter attiva (no trashed).
        $org = Organization::query()
            ->where('type', 'renter')
            ->whereKey($organizationId)
            ->firstOrFail();

        /**
         * Indicatori senza decrypt:
         * usiamo getRawOriginal per non forzare la decifratura dei campi.
         */
        $this->hasCargosPassword = ! empty($org->getRawOriginal('cargos_password'));
        $this->hasCargosPuk      = ! empty($org->getRawOriginal('cargos_puk'));

        $this->open = true;
    }

    /**
     * Chiude il modale e ripulisce.
     */
    public function closeModal(): void
    {
        $this->open = false;
        $this->organizationId = null;

        $this->hasCargosPassword = false;
        $this->hasCargosPuk = false;

        $this->state['cargos_password'] = null;
        $this->state['cargos_puk'] = null;

        $this->confirmPassword = '';
        $this->revealedCargosPassword = null;
        $this->revealedCargosPuk = null;

        $this->resetValidation();
    }

    /**
     * Rimuove i valori rivelati (auto-hide).
     */
    public function hideReveals(): void
    {
        $this->revealedCargosPassword = null;
        $this->revealedCargosPuk = null;
        $this->confirmPassword = '';
    }

    /**
     * Reveal: richiede password admin e mostra temporaneamente i valori.
     * - $field: 'password' | 'puk'
     */
    public function reveal(string $field): void
    {
        $this->resetErrorBag('confirmPassword');

        $user = Auth::user();

        // Guardia: solo admin
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403);
        }

        // Deve esserci un organizationId valido
        if (empty($this->organizationId)) {
            return;
        }

        // Conferma password admin obbligatoria
        if (trim($this->confirmPassword) === '') {
            $this->addError('confirmPassword', 'Inserisci la password admin per visualizzare i dati.');
            return;
        }

        if (! Hash::check($this->confirmPassword, (string) $user->password)) {
            $this->addError('confirmPassword', 'Password admin non valida.');
            return;
        }

        // Carica renter attivo e decrypt tramite cast "encrypted" (solo qui, su richiesta).
        $org = Organization::query()
            ->where('type', 'renter')
            ->whereKey($this->organizationId)
            ->firstOrFail();

        if ($field === 'password') {
            $this->revealedCargosPassword = $org->cargos_password;
        }

        if ($field === 'puk') {
            $this->revealedCargosPuk = $org->cargos_puk;
        }

        // Svuota la password admin dopo reveal (igiene).
        $this->confirmPassword = '';

        // Toast informativo (opzionale ma utile).
        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'Dati Cargos visualizzati temporaneamente.',
            'duration' => 2500,
        ]);
    }

    /**
     * Salva i cargos (se i campi sono vuoti, non sovrascriviamo).
     */
    public function save(): void
    {
        $this->validate();

        if (empty($this->organizationId)) {
            return;
        }

        try {
            // Renter attivo (no trashed)
            $org = Organization::query()
                ->where('type', 'renter')
                ->whereKey($this->organizationId)
                ->firstOrFail();

            $updates = [];

            // Se l'admin ha inserito un nuovo valore, aggiorniamo; altrimenti manteniamo quello attuale.
            if (! empty($this->state['cargos_password'])) {
                $updates['cargos_password'] = $this->state['cargos_password'];
            }

            if (! empty($this->state['cargos_puk'])) {
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

            // Aggiorna indicatori
            $this->hasCargosPassword = ! empty($org->getRawOriginal('cargos_password'));
            $this->hasCargosPuk      = ! empty($org->getRawOriginal('cargos_puk'));

            // Pulizia campi inseriti
            $this->state['cargos_password'] = null;
            $this->state['cargos_puk'] = null;
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
                'message' => 'Errore durante il salvataggio dei Cargos. Riprova.',
                'duration' => 4500,
            ]);

        } catch (\Throwable $e) {
            Log::error('CargosModal save Throwable', [
                'organization_id' => $this->organizationId,
                'message' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Errore durante il salvataggio dei Cargos. Riprova.',
                'duration' => 4500,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.organizations.cargos-modal');
    }
}
