<?php

namespace App\Livewire\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Livewire: Sedi ▸ Tabella
 *
 * - Ricerca stile organizations: input singolo, '*'→'%', spazi→'%'.
 * - Paginazione e sort (sicuro) su 'name' e 'city'.
 * - Scope tenant per renter.
 * - Niente flash, solo toast (gestiti globalmente nel layout).
 */
class Table extends Component
{
    use WithPagination, AuthorizesRequests;

    /** Ricerca libera (persistita in URL) */
    #[Url(as: 'q')]
    public ?string $search = null;

    /** Ordinamento sicuro */
    public string $sort = 'name';
    public string $dir  = 'asc';

    /** Per pagina */
    public int $perPage = 15;

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    /** Toggle dell'ordinamento come nelle altre viste */
    public function setSort(string $column): void
    {
        if (! in_array($column, ['name', 'city', 'created_at'], true)) {
            return;
        }
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

        // Normalizzazione ricerca (lowercase, '*'→'%', spazi→'%')
        $term = $this->search ? strtolower(trim($this->search)) : null;
        if ($term) {
            $term = str_replace('*', '%', $term);
            $term = preg_replace('/\s+/', '%', $term);
        }
        // Inizializzo SEMPRE $like per evitare "Undefined variable $like"
        $like = $term ? "%{$term}%" : null;

        $query = Location::query()
            // Scope tenant se l'utente è renter (se hai un helper isRenter)
            ->when(
                $user->organization && method_exists($user->organization, 'isRenter') && $user->organization->isRenter(),
                fn (Builder $q) => $q->where('organization_id', $orgId)
            )
            // Ricerca multi-campo
            ->when($term, function (Builder $q) use ($like) {
                $q->where(function (Builder $w) use ($like) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(city) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(postal_code) LIKE ?', [$like]);
                });
            })
            // Ordinamento sicuro
            ->when(in_array($this->sort, ['name','city','created_at'], true),
                fn (Builder $q) => $q->orderBy($this->sort, $this->dir === 'asc' ? 'asc' : 'desc'),
                fn (Builder $q) => $q->orderBy('name', 'asc')
            );

        return view('livewire.locations.table', [
            'locations' => $query->paginate($this->perPage),
        ]);
    }
}
