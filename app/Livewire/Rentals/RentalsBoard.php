<?php

namespace App\Livewire\Rentals;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Rental;
use Illuminate\Contracts\View\View;

class RentalsBoard extends Component
{
    use WithPagination;

    /** Vista corrente: 'table' | 'kanban' */
    #[Url(as: 'view', except: 'table')]
    public string $view = 'table';

    /** Stato selezionato: default 'draft' (Bozze) */
    #[Url(as: 'state', except: 'draft')]
    public ?string $state = 'draft';

    /** Ricerca libera */
    public string $q = '';

    /** Etichette in italiano per stati */
    public array $stateLabels = [
        'draft'       => 'Bozze',
        'reserved'    => 'Prenotati',
        'checked_out' => 'Consegnati',
        'in_use'      => 'In uso',
        'checked_in'  => 'Rientrati',
        'closed'      => 'Chiusi',
        'cancelled'   => 'Cancellati',
        'no_show'     => 'No-show',
    ];

    /** Classi colore (card KPI / badge) per stato */
    public array $stateColors = [
        'draft'       => 'bg-gray-100 border-gray-300 text-gray-800',
        'reserved'    => 'bg-blue-100 border-blue-300 text-blue-900',
        'checked_out' => 'bg-amber-100 border-amber-300 text-amber-900',
        'in_use'      => 'bg-slate-100 border-slate-300 text-slate-900',
        'checked_in'  => 'bg-indigo-100 border-indigo-300 text-indigo-900',
        'closed'      => 'bg-green-100 border-green-300 text-green-900',
        'cancelled'   => 'bg-rose-100 border-rose-300 text-rose-900',
        'no_show'     => 'bg-rose-100 border-rose-300 text-rose-900',
    ];

    protected $queryString = [
        'view'  => ['as' => 'view', 'except' => 'table'],
        // manteniamo 'draft' come default: non salvare in querystring finché è 'draft'
        'state' => ['as' => 'state', 'except' => 'draft'],
        'q'     => ['as' => 'q', 'except' => ''],
    ];

    /** Ordine colonne/pulsanti KPI */
    public function getStatesProperty(): array
    {
        return ['draft','reserved','checked_out','in_use','checked_in','closed','cancelled','no_show'];
    }

    /** KPI per stato (rispettando eventuale ricerca) */
    public function getKpisProperty(): array
    {
        $kpis = [];
        foreach ($this->states as $s) {
            $kpis[$s] = Rental::query()
                ->when($this->q, fn($qb) => $qb
                    ->where('reference', 'like', '%'.$this->q.'%')
                    ->orWhereHas('customer', fn($qq) => $qq->where('name','like','%'.$this->q.'%'))
                )
                ->where('status', $s)
                ->count();
        }
        return $kpis;
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['table','kanban'], true) ? $view : 'table';
        $this->resetPage();
    }

    public function filterState(?string $state): void
    {
        $this->state = $state ?: 'draft';
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Rental::query()
            ->with(['customer','vehicle'])
            ->when($this->state, fn($qb) => $qb->where('status', $this->state))
            ->when($this->q, fn($qb) => $qb
                ->where('id', 'like', '%'.$this->q.'%')
                ->orWhereHas('customer', fn($qq) => $qq->where('name','like','%'.$this->q.'%'))
            )
            ->orderByDesc('id');

        $rows = $this->view === 'table'
            ? $query->paginate(15)
            : $query->limit(200)->get();

        return view('livewire.rentals.board', [
            'rows' => $rows,
            'kpis' => $this->kpis,
        ]);
    }
}
