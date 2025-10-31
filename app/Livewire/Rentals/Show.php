<?php

namespace App\Livewire\Rentals;

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Rental;
use Illuminate\Contracts\View\View;
use App\Services\Contracts\GenerateRentalContract;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Livewire: Scheda noleggio con tab e Action Drawer.
 *
 * - Non modifica nomi/relazioni esistenti.
 * - Le transizioni di stato invocano rotte POST di RentalController (submit form hidden).
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** @var Rental Istanza di noleggio */
    public Rental $rental;

    /** @var string Tab attivo: data|contract|pickup|return|damages|attachments|timeline */
    public string $tab = 'data';

    protected $queryString = [
        'tab' => ['as' => 'tab', 'except' => 'data']
    ];

    public function mount(Rental $rental): void
    {
        $this->rental = $rental->load(['customer','vehicle','checklists','damages']);
    }

    public function switch(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * Genera una nuova versione del contratto (PDF) e la salva su Media Library (collection "contract").
     * - Autorizza l'utente (policy/permessi).
     * - Usa il service GenerateRentalContract (già fornito).
     * - Mostra un toast e aggiorna il componente Livewire senza navigare.
     */
    public function generateContract(GenerateRentalContract $generator): void
    {
        // 🔐 Autorizzazione
        if (!auth()->user()?->can('rentals.contract.generate') || !auth()->user()?->can('media.attach.contract')) {
            // Livewire v3: dispatch() crea un evento browser "toast" (il tuo layout lo ascolta)
            $this->dispatch('toast', type: 'error', message: 'Permesso negato.');
            return;
        }

        try {
            // ⚙️ Generazione + salvataggio su Media Library (marca "current" solo l’ultimo: già nel service)
            $generator->handle($this->rental);

            // 🔄 Aggiorna il model e la UI
            $this->rental->refresh();
            $this->dispatch('toast', type: 'success', message: 'Contratto generato.');
            $this->dispatch('$refresh'); // forza il re-render del componente

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Errore durante la generazione del contratto.');
        }
    }

    public function render(): View
    {
        return view('livewire.rentals.show');
    }
}
