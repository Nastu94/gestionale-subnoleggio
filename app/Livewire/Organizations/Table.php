<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Livewire: Tabella Renter (Organizations type='renter')
 *
 * - Ricerca per nome
 * - Filtro per numero veicoli assegnati OGGI (tutti, zero, >0)
 * - Ordinamento per nome o vehicles_count
 * - Paginazione
 * - Pulsanti "Modifica" / "Elimina" nella riga espansa
 *
 * Nota: NON esegue create/update/delete; apre soltanto il modale
 * tramite eventi browser. Il salvataggio resterà ai controller HTTP.
 */
class Table extends Component
{
    use WithPagination;

    /** Ricerca fulltext "povera" sul nome */
    public string $search = '';

    /** Campo di ordinamento: 'name' | 'vehicles_count' */
    public string $sort = 'name';

    /** Direzione di ordinamento: 'asc' | 'desc' */
    public string $dir = 'asc';

    /** Elementi per pagina */
    public int $perPage = 15;

    /**
     * Filtro sul conteggio veicoli assegnati OGGI:
     * - 'all' (tutti) | 'zero' (nessun veicolo) | 'gt0' (almeno uno)
     */
    public string $countFilter = 'all';

    /**
     * Query string “pulita”: mantiene lo stato della tabella tra refresh/navigazione.
     */
    protected array $queryString = [
        'search'      => ['except' => ''],
        'sort'        => ['except' => 'name'],
        'dir'         => ['except' => 'asc'],
        'perPage'     => ['except' => 15],
        'countFilter' => ['except' => 'all'],
        'page'        => ['except' => 1],
    ];

    /** Reset paginazione quando cambiano questi filtri */
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }
    public function updatedCountFilter(): void { $this->resetPage(); }

    /**
     * Sicurezza: solo admin devono poter usare questo componente.
     * La pagina è già protetta dal Gate 'manage.renters', ma qui
     * mettiamo un guard addizionale a prova di mount diretto.
     */
    public function mount(): void
    {
        if (! Gate::allows('manage.renters')) {
            abort(403);
        }
    }

    /**
     * Imposta l’ordinamento. Se clicco di nuovo sullo stesso campo, inverte la direzione.
     */
    public function setSort(string $field): void
    {
        if (! in_array($field, ['name', 'vehicles_count'], true)) {
            return;
        }
        if ($this->sort === $field) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->dir  = 'asc';
        }
        $this->resetPage();
    }

    /**
     * Apre il modale “nuovo renter” (il modale è gestito da Alpine nella pagina).
     */
    public function openCreate(): void
    {
        // Livewire v3: evento verso il browser (catturato da Alpine in pages/organizations/index.blade.php)
        $this->dispatch('open-org-create');
    }

    /**
     * Apre il modale in modalità edit caricando i dati del renter.
     */
    public function openEdit(int $organizationId): void
    {
        $org = Organization::query()
            ->where('type', 'renter')
            ->findOrFail($organizationId);

        $this->dispatch('open-org-edit', org: [
            'id'   => $org->id,
            'name' => $org->name,
        ]);
    }

    /**
     * Genera il paginator con tutte le feature (ricerca, filtro, sort).
     * Calcola vehicles_count con una LEFT JOIN su subquery aggregata
     * che conta i veicoli assegnati OGGI (intervallo che interseca il giorno corrente).
     */
    private function paginateOrganizations(): LengthAwarePaginator
    {
        // Boundaries del giorno corrente nel timezone app
        $startOfToday = Carbon::now()->startOfDay();
        $endOfToday   = Carbon::now()->endOfDay();

        // Subquery: conteggio veicoli assegnati oggi per org
        $assignTodaySub = DB::table('vehicle_assignments as va')
            ->selectRaw('va.renter_org_id as org_id, COUNT(DISTINCT va.vehicle_id) as cnt')
            ->where('va.start_at', '<=', $endOfToday)
            ->where(function (BaseBuilder $q) use ($startOfToday) {
                $q->whereNull('va.end_at')
                  ->orWhere('va.end_at', '>=', $startOfToday);
            })
            ->groupBy('va.renter_org_id');

        // Query principale: organizations renter + join subquery
        $q = Organization::query()
            ->from('organizations')
            ->where('organizations.type', 'renter')
            ->leftJoinSub($assignTodaySub, 'ac', function ($join) {
                $join->on('ac.org_id', '=', 'organizations.id');
            })
            ->select([
                'organizations.*',
                DB::raw('COALESCE(ac.cnt, 0) as vehicles_count'),
            ])
            ->when($this->search !== '', function ($q) {
                $s = '%' . str_replace(['%', '_'], ['\%', '\_'], $this->search) . '%';
                $q->where('organizations.name', 'like', $s);
            });

        // Filtro per numero veicoli (usa direttamente l’alias della join)
        $q->when($this->countFilter === 'zero', fn($q) => $q->whereRaw('COALESCE(ac.cnt,0) = 0'))
          ->when($this->countFilter === 'gt0',  fn($q) => $q->whereRaw('COALESCE(ac.cnt,0) > 0'));

        // Ordinamento: gestisci alias 'vehicles_count', altrimenti ordina per colonna reale
        if ($this->sort === 'vehicles_count') {
            $q->orderBy(DB::raw('vehicles_count'), $this->dir);
        } else {
            // sicurezza: il default è 'name'; qualora venga altro valore, fallback
            $column = $this->sort === 'name' ? 'organizations.name' : 'organizations.name';
            $q->orderBy($column, $this->dir);
        }

        return $q->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.organizations.table', [
            'organizations' => $this->paginateOrganizations(),
            'sort'  => $this->sort,
            'dir'   => $this->dir,
        ]);
    }
}
