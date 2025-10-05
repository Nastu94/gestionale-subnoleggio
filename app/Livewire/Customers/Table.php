<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Livewire: Clienti ▸ Tabella readonly
 *
 * - Ricerca stile "organizations": input singolo, debounce, spazi→% e '*'→'%'.
 * - Paginazione e ordinamento base su 'name'.
 * - Scope per tenant se l'utente è renter.
 * - NESSUNA creazione dalla lista (coerente con creazione da wizard contratto).
 */
class Table extends Component
{
    use WithPagination, AuthorizesRequests;

    /** Ricerca libera (persistita in URL per UX) */
    #[Url(as: 'q')]
    public ?string $search = null;

    /** Ordinamento: colonna e direzione (stile organizations) */
    public string $sort = 'name';
    public string $dir  = 'asc';

    /** Per pagina (stesse opzioni della tua tabella) */
    public int $perPage = 15;

    /** Reset pagina quando cambiano filtri/ricerca */
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    public function mount(): void
    {
        // Autorizzazione: viewAny su Customer
        $this->authorize('viewAny', Customer::class);
    }

    /**
     * Imposta colonna di ordinamento (toggle asc/desc se la colonna è la stessa).
     */
    public function setSort(string $column): void
    {
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
        $user  = Auth::user();
        $orgId = (int) $user->organization_id;

        // Normalizzazione ricerca: lower, '*'→'%', spazi→'%' (come richiesto)
        $term = $this->search ? strtolower(trim($this->search)) : null;
        if ($term) {
            $term = str_replace('*', '%', $term);
            $term = preg_replace('/\s+/', '%', $term);
        }
        // Importante: inizializzo SEMPRE $like per evitare "Undefined variable $like"
        $like = $term ? "%{$term}%" : null;

        $query = Customer::query()
            // Scope tenant per renter (se hai un helper isRenter sull'organizzazione)
            ->when(
                $user->organization && method_exists($user->organization, 'isRenter') && $user->organization->isRenter(),
                fn (Builder $q) => $q->where('organization_id', $orgId)
            )
            // Ricerca multi-campo
            ->when($term, function (Builder $q) use ($like) {
                $q->where(function (Builder $w) use ($like) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(doc_id_number) LIKE ?', [$like]);
                });
            })
            // Ordinamento base (limitiamo alle colonne sicure)
            ->when(in_array($this->sort, ['name', 'created_at']), function (Builder $q) {
                $q->orderBy($this->sort, $this->dir === 'asc' ? 'asc' : 'desc');
            }, function (Builder $q) {
                $q->orderBy('name', 'asc');
            });

        return view('livewire.customers.table', [
            'customers' => $query->paginate($this->perPage),
        ]);
    }
}
