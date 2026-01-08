<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Table extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sort = 'name';     // 'name' | 'vehicles_count'
    public string $dir  = 'asc';
    public int    $perPage = 15;
    public string $countFilter = 'all'; // 'all' | 'zero' | 'gt0'

    protected array $queryString = [
        'search'      => ['except' => ''],
        'sort'        => ['except' => 'name'],
        'dir'         => ['except' => 'asc'],
        'perPage'     => ['except' => 15],
        'countFilter' => ['except' => 'all'],
        'page'        => ['except' => 1],
        'statusFilter' => ['except' => 'active'],
    ];

    public function updatedSearch(): void      { $this->resetPage(); }
    public function updatedPerPage(): void     { $this->resetPage(); }
    public function updatedCountFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage();}

    /**
     * Filtro stato renter:
     * - active: solo attive (default)
     * - trashed: solo archiviate (soft delete)
     * - all: tutte (attive + archiviate)
     */
    public string $statusFilter = 'active';

    public function mount(): void
    {
        if (! Gate::allows('manage.renters')) {
            abort(403);
        }
    }

    /** Ordina per campo, invertendo direzione se ricliccato. */
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

    /** Apre modale di creazione (evento browser intercettato da Alpine). */
    public function openCreate(): void
    {
        $this->dispatch('open-org-create');
    }

    /**
     * Apre il modale in modalità "crea SOLO utente per renter esistente".
     */
    public function openAddUser(int $organizationId): void
    {
        $org = \App\Models\Organization::query()
            ->where('type', 'renter')
            ->findOrFail($organizationId);

        // Nessun utente precompilato: il modale saprà che deve creare un nuovo user
        $this->dispatch('open-org-add-user', org: [
            'id'   => $org->id,
            'name' => $org->name,
        ]);
    }

    /**
     * Apre modale di modifica.
     * - Se $userId è fornito, carica quell'utente; altrimenti prende il primo dell'org.
     */
    public function openEdit(int $organizationId, ?int $userId = null): void
    {
        $org = Organization::query()
            ->where('type', 'renter')
            ->findOrFail($organizationId);

        $user = User::query()
            ->where('organization_id', $org->id)
            ->when($userId, fn($q) => $q->whereKey($userId))
            ->orderBy('id')
            ->first();

        $this->dispatch('open-org-edit',
            org: ['id' => $org->id, 'name' => $org->name],
            user: $user ? ['id'=>$user->id, 'name'=>$user->name, 'email'=>$user->email] : null
        );
    }

    /**
     * Costruisce il paginator:
     *  - LEFT JOIN su subquery di conteggio veicoli assegnati OGGI (overlap)
     *  - LEFT JOIN su users per avere una riga per ogni utente dell'organizzazione
     *  - Ricerca su nome org, nome utente, mail
     */
    private function paginateOrganizations(): LengthAwarePaginator
    {
        $startOfToday = Carbon::now()->startOfDay();
        $endOfToday   = Carbon::now()->endOfDay();

        // Subquery: veicoli assegnati OGGI (overlap)
        $assignTodaySub = DB::table('vehicle_assignments as va')
            ->selectRaw('va.renter_org_id as org_id, COUNT(DISTINCT va.vehicle_id) as cnt')
            ->where('va.start_at', '<=', $endOfToday)
            ->where(function (BaseBuilder $q) use ($startOfToday) {
                $q->whereNull('va.end_at')
                  ->orWhere('va.end_at', '>=', $startOfToday);
            })
            ->groupBy('va.renter_org_id');

        // Query principale: una riga per (organization x user) – le org senza utenti compaiono comunque (user_* = NULL)
        $q = Organization::query()
            ->from('organizations')
            ->where('organizations.type', 'renter')
            ->leftJoinSub($assignTodaySub, 'ac', function ($join) {
                $join->on('ac.org_id', '=', 'organizations.id');
            })
            ->leftJoin('users', function ($join) {
                $join->on('users.organization_id', '=', 'organizations.id')
                     ->whereNull('users.deleted_at');
            })
            ->select([
                'organizations.*',
                DB::raw('COALESCE(ac.cnt, 0) as vehicles_count'),
                'users.id   as user_id',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->when($this->search !== '', function ($q) {
                /**
                 * Costruisci il pattern LIKE:
                 * - trim e lowercase (coerente con LOWER(...) lato SQL)
                 * - '*' diventa '%' (wildcard amichevole)
                 * - spazi → '%' per consentire ricerche multi-parola "staccate"
                 * - NON si escapa '%' o '_' per permettere all'utente wildcard manuali
                 * - incapsula con %...% per match "contiene"
                 */
                $term = mb_strtolower(trim($this->search), 'UTF-8');
                $term = str_replace('*', '%', $term);
                $term = preg_replace('/\s+/', '%', $term);
                $like = "%{$term}%";

                // Ricerca su nome renter, nome utente, email (in lowercase per coerenza)
                $q->where(function ($w) use ($like) {
                    $w->whereRaw('LOWER(organizations.name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(users.email) LIKE ?', [$like]);
                });
            });

        // Filtro per numero veicoli assegnati oggi
        $q->when($this->countFilter === 'zero', fn($q) => $q->whereRaw('COALESCE(ac.cnt,0) = 0'))
          ->when($this->countFilter === 'gt0',  fn($q) => $q->whereRaw('COALESCE(ac.cnt,0) > 0'));

        /**
         * Filtro stato renter:
         * - active: default (nessuna modifica, esclude i trashed per global scope)
         * - trashed: solo archiviate
         * - all: include anche archiviate
         */
        $q->when($this->statusFilter === 'trashed', fn ($q) => $q->onlyTrashed())
          ->when($this->statusFilter === 'all', fn ($q) => $q->withTrashed());


        // Ordinamento
        if ($this->sort === 'vehicles_count') {
            $q->orderBy(DB::raw('vehicles_count'), $this->dir)->orderBy('organizations.name'); // tie-breaker
        } else {
            $q->orderBy('organizations.name', $this->dir)->orderBy('users.name');
        }

        return $q->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.organizations.table', [
            // Mantengo il nome 'organizations' per compatibilità con la Blade
            'organizations' => $this->paginateOrganizations(),
            'sort' => $this->sort,
            'dir'  => $this->dir,
        ]);
    }
}
