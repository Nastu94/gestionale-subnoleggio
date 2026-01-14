<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tabella dei contratti (noleggi) per un dato cliente.
 * - Readonly, toast-only (nessun redirect/flash).
 * - Stile coerente con le altre tabelle (ricerca + perPage + sort limitato).
 * - Scope sul cliente passato (tenant-safe perché già autorizzato dal dettaglio cliente).
 */
class RentalsTable extends Component
{
    use WithPagination, AuthorizesRequests;

    /** Cliente corrente (iniettato dal genitore) */
    public Customer $customer;

    /** Ricerca libera: id contratto, stato, targa veicolo */
    #[Url(as: 'q')]
    public ?string $search = null;

    /** Ordinamento: sicuro su id (desc default) */
    public string $sort = 'id';
    public string $dir  = 'desc';

    /** Paginazione */
    public int $perPage = 10;

    public function mount(Customer $customer): void
    {
        // L'utente deve poter vedere i noleggi (viewAny è sufficiente, il filtro è sul customer).
        $this->authorize('viewAny', Rental::class);
        $this->customer = $customer;
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    /** Toggle ordinamento (limitato a colonne sicure) */
    public function setSort(string $column): void
    {
        if (! in_array($column, ['id', 'status', 'created_at'], true)) return;

        if ($this->sort === $column) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->dir  = 'asc';
        }
        $this->resetPage();
    }

    public function render()
    {
        // Normalizzazione ricerca: lower, '*'→'%', spazi→'%'
        $term = $this->search ? strtolower(trim($this->search)) : null;
        if ($term) {
            $term = str_replace('*', '%', $term);
            $term = preg_replace('/\s+/', '%', $term);
        }
        $like = $term ? "%{$term}%" : null;

        $query = Rental::query()
            ->with(['vehicle']) // relazione tipica; non rinomino

            /**
             * Tenant-scope:
             * - Se chi guarda è un renter, deve vedere SOLO i rentals della sua organization.
             * - Se è admin, può vedere tutto (nessun filtro).
             */
            ->when(auth()->user()?->organization?->isRenter(), function (Builder $q) {
                $q->where('organization_id', auth()->user()->organization_id);
            })

            /**
             * Il customer può comparire:
             * - come intestatario (customer_id)
             * - come seconda guida (second_driver_id)
             *
             * Raggruppo con una closure per mantenere la logica corretta in SQL.
             */
            ->where(function (Builder $q) {
                $q->where('customer_id', $this->customer->id)
                ->orWhere('second_driver_id', $this->customer->id);
            })

            /**
             * Ricerca:
             * IMPORTANTISSIMO: raggruppare le OR in una sotto-clausola,
             * altrimenti possono bypassare i vincoli sopra (tenant/customer).
             */
            ->when($term, function (Builder $q) use ($term, $like) {
                $q->where(function (Builder $w) use ($term, $like) {
                    $w->whereRaw('LOWER(status) LIKE ?', [$like])
                    ->orWhere('id', (int) $term)
                    ->orWhereHas('vehicle', fn (Builder $v) =>
                        $v->whereRaw('LOWER(plate) LIKE ?', [$like])
                    );
                });
            })

            ->when(in_array($this->sort, ['id', 'status', 'created_at'], true),
                fn (Builder $q) => $q->orderBy($this->sort, $this->dir === 'asc' ? 'asc' : 'desc'),
                fn (Builder $q) => $q->orderBy('id', 'desc')
            );

        return view('livewire.customers.rentals-table', [
            'rentals' => $query->paginate($this->perPage),
        ]);
    }
}
