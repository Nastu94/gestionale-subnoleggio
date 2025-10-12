<?php

namespace App\Livewire\Rentals;

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Rental;
use Illuminate\Contracts\View\View;

/**
 * Livewire: Scheda noleggio con tab e Action Drawer.
 *
 * - Non modifica nomi/relazioni esistenti.
 * - Le transizioni di stato invocano rotte POST di RentalController (submit form hidden).
 */
class Show extends Component
{
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

    public function render(): View
    {
        return view('livewire.rentals.show');
    }
}
