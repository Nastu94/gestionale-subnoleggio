<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

/**
 * Form profilo: gestione credenziali Cargos dell'Admin (password + PUK).
 *
 * Sicurezza:
 * - Non inviamo mai i valori in chiaro nel payload Livewire se non dopo "reveal".
 * - Per "reveal" richiediamo conferma password admin.
 * - Auto-hide dei valori rivelati lato UI.
 *
 * Persistenza:
 * - I valori stanno nel file .env (NON in DB).
 * - In salvataggio aggiorniamo .env e puliamo la config cache.
 */
class UpdateAdminCargosForm extends Component
{
    /**
     * Indicatori non sensibili: dicono se i valori risultano impostati.
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
     * Conferma password admin per rivelare i valori.
     */
    public string $confirmPassword = '';

    /**
     * Valori rivelati (solo dopo conferma). Non persistono qui.
     */
    public ?string $revealedCargosPassword = null;
    public ?string $revealedCargosPuk = null;

    /**
     * Mount: accesso solo admin.
     */
    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            abort(403);
        }

        $this->refreshFlags();
    }

    /**
     * Regole validazione per salvataggio (non per reveal).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'state.cargos_password' => ['nullable', 'string', 'max:255'],
            'state.cargos_puk'      => ['nullable', 'string', 'max:255'],
            'confirmPassword'       => ['nullable', 'string'],
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
     * Ricarica gli indicatori "impostato/non impostato" senza esporre segreti.
     */
    private function refreshFlags(): void
    {
        $pwd = (string) (config('cargos.admin.password') ?? '');
        $puk = (string) (config('cargos.admin.puk') ?? '');

        $this->hasCargosPassword = trim($pwd) !== '';
        $this->hasCargosPuk      = trim($puk) !== '';
    }

    /**
     * Nasconde i valori rivelati (auto-hide).
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

        // Conferma password admin obbligatoria
        if (trim($this->confirmPassword) === '') {
            $this->addError('confirmPassword', 'Inserisci la password admin per visualizzare i dati.');
            return;
        }

        if (! Hash::check($this->confirmPassword, (string) $user->password)) {
            $this->addError('confirmPassword', 'Password admin non valida.');
            return;
        }

        // Carica da config (che legge da .env)
        if ($field === 'password') {
            $this->revealedCargosPassword = (string) (config('cargos.admin.password') ?? '');
        }

        if ($field === 'puk') {
            $this->revealedCargosPuk = (string) (config('cargos.admin.puk') ?? '');
        }

        // Igiene: svuota la password admin dopo il reveal
        $this->confirmPassword = '';

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => 'Dati Cargos visualizzati temporaneamente.',
            'duration' => 2500,
        ]);
    }

    /**
     * Salva: aggiorna .env con i valori inseriti (se non vuoti).
     * I valori restano in .env e vengono riletti tramite config('cargos.*').
     */
    public function save(): void
    {
        $this->validate();

        $updates = [];

        if (! empty($this->state['cargos_password'])) {
            $updates['CARGOS_ADMIN_PASSWORD'] = (string) $this->state['cargos_password'];
        }

        if (! empty($this->state['cargos_puk'])) {
            $updates['CARGOS_ADMIN_PUK'] = (string) $this->state['cargos_puk'];
        }

        if (empty($updates)) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => 'Nessuna modifica da salvare.',
                'duration' => 3000,
            ]);
            return;
        }

        try {
            $this->updateEnvFile($updates);

            /**
             * IMPORTANTISSIMO:
             * - Se hai config cache, config('...') non si aggiorna finché non pulisci.
             * - Anche senza cache, è buona igiene dopo update .env.
             */
            Artisan::call('config:clear');

            // Ripulisci input “nuovi valori”
            $this->state['cargos_password'] = null;
            $this->state['cargos_puk'] = null;

            // Nascondi eventuali reveal
            $this->hideReveals();

            // Refresh indicatori
            $this->refreshFlags();

            $this->dispatch('saved');
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Credenziali Cargos (Admin) aggiornate in .env.',
                'duration' => 3000,
            ]);
        } catch (\Throwable $e) {
            Log::error('UpdateAdminCargosForm save error', [
                'message' => $e->getMessage(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Errore durante il salvataggio in .env. Verifica permessi del file.',
                'duration' => 4500,
            ]);
        }
    }

    /**
     * Aggiorna/aggiunge chiavi nel file .env in modo semplice e robusto.
     * - Se la chiave esiste: la sostituisce.
     * - Se non esiste: la appende.
     *
     * @param array<string,string> $pairs
     */
    private function updateEnvFile(array $pairs): void
    {
        $envPath = base_path('.env');

        if (! is_file($envPath) || ! is_readable($envPath) || ! is_writable($envPath)) {
            throw new \RuntimeException('File .env non leggibile/scrivibile.');
        }

        $content = (string) file_get_contents($envPath);

        foreach ($pairs as $key => $value) {
            $safeValue = $this->dotenvQuote($value);

            $pattern = "/^{$key}=.*$/m";

            if (preg_match($pattern, $content) === 1) {
                $content = preg_replace($pattern, "{$key}={$safeValue}", $content);
            } else {
                // assicura newline finale
                if ($content !== '' && ! str_ends_with($content, "\n")) {
                    $content .= "\n";
                }
                $content .= "{$key}={$safeValue}\n";
            }
        }

        file_put_contents($envPath, $content, LOCK_EX);
    }

    /**
     * Quota un valore per .env:
     * - Usiamo sempre doppi apici per evitare problemi con spazi, #, ecc.
     * - Escape di backslash e doppi apici.
     */
    private function dotenvQuote(string $value): string
    {
        $v = str_replace('\\', '\\\\', $value);
        $v = str_replace('"', '\"', $v);

        return "\"{$v}\"";
    }

    public function render()
    {
        return view('profile.update-admin-cargos-form');
    }
}
