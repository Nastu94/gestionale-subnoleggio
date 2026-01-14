<?php

namespace App\Livewire\Documents;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\Organization;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestione documenti veicoli (lista + filtri + editor in drawer, senza allegati).
 * - Filtri persistiti via query string.
 * - Renter: vede solo i veicoli a lui assegnati *ora* (join vehicle_assignments).
 * - Permessi granulari: create/update/delete (admin) ; update (renter).
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    // ---------------- Filtri (persistiti in URL) ----------------
    #[Url(as: 'q')]         public ?string $search = null;
    #[Url(as: 'type')]      public ?string $type = null;     // insurance|road_tax|inspection|registration|green_card|ztl_permit|other
    #[Url(as: 'state')]     public ?string $state = null;    // expired|soon30|soon60|ok|no_date
    #[Url(as: 'vehicle_id')]public ?int $vehicleId = null;
    #[Url(as: 'org')]       public ?int $orgId = null;
    #[Url(as: 'loc')]       public ?int $locId = null;
    #[Url(as: 'from')]      public ?string $from = null;     // YYYY-MM-DD
    #[Url(as: 'to')]        public ?string $to = null;       // YYYY-MM-DD
    #[Url(as: 'sort')]      public string $sort = 'expiry_date';
    #[Url(as: 'dir')]       public string $dir = 'asc';
    #[Url(as: 'per_page')]  public int $perPage = 25;
    #[Url(as: 'archived')]  public bool $showArchived = false;

    // ---------------- Selezione / UI ----------------
    /** ID documenti selezionati (tutte le pagine) */
    public array $selected = [];

    /** Data di rinnovo per l’azione bulk (YYYY-MM-DD) */
    public ?string $bulkRenewDate = null;

    /** Drawer editor */
    public bool $drawerOpen = false;
    public ?int $editingId = null;
    public ?bool $editingVehicleArchived = null;

    /** Form editor (no allegati) */
    public array $form = [
        'vehicle_id'  => null,
        'type'        => null,
        'number'      => null,
        'expiry_date' => null,
    ];

    /** Etichette italiane per enum documenti */
    public array $docLabels = [
        'insurance'    => 'RCA',
        'road_tax'     => 'Bollo',
        'inspection'   => 'Revisione',
        'registration' => 'Libretto',
        'green_card'   => 'Carta verde',
        'ztl_permit'   => 'Permesso ZTL',
        'other'        => 'Altro',
    ];

    protected array $allowedSort  = ['expiry_date','type','number','vehicle.plate'];
    protected array $allowedTypes = ['insurance','road_tax','inspection','registration','green_card','ztl_permit','other'];

    protected function rules(): array
    {
        return [
            'form.vehicle_id'  => ['required','integer','exists:vehicles,id'],
            'form.type'        => ['required','in:'.implode(',', $this->allowedTypes)],
            'form.number'      => ['nullable','string','max:100'],
            'form.expiry_date' => ['nullable','date'],
        ];
    }

    /**
     * Init:
     * - Enforce scoping: i renter non possono usare il filtro organizzazione.
     */
    public function mount(): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.manage')) {
            $this->orgId = null;
        }
    }

    // Reset pagina quando cambiano i filtri
    public function updated($name, $value): void
    {
        $filters = ['search','type','state','vehicleId','orgId','locId','from','to','perPage','showArchived','sort','dir'];
        if (in_array($name, $filters, true)) {
            $this->resetPage();
        }
    }
    public function updatedType($value): void
    {
        if ($value !== null && !in_array($value, $this->allowedTypes, true)) $this->type = null;
    }
    public function updatedDir($value): void
    {
        if (!in_array($value, ['asc','desc'], true)) $this->dir = 'asc';
    }
    public function updatedSort($value): void
    {
        if (!in_array($value, $this->allowedSort, true)) $this->sort = 'expiry_date';
    }

    // ---------------- Azioni toolbar/editor ----------------

    /** Apre drawer in modalità CREATE (solo chi può creare) */
    public function openCreate(): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.create') && !Auth::user()->can('vehicle_documents.manage')) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per creare documenti.']);
            return;
        }

        if (!$this->vehicleId) {
            $this->dispatch('toast', ['type' => 'info', 'message' => 'Seleziona prima un veicolo dal filtro "ID veicolo".']);
            return;
        }

        $vehicle = Vehicle::withTrashed()->findOrFail($this->vehicleId);
        $this->editingVehicleArchived = method_exists($vehicle, 'trashed') && $vehicle->trashed();

        $this->editingId = null;
        $this->form = [
            'vehicle_id'  => $vehicle->id,
            'type'        => null,
            'number'      => null,
            'expiry_date' => null,
        ];
        $this->drawerOpen = true;
    }

    /** Apre drawer in modalità EDIT (view per tutti, write se permesso) */
    public function openEdit(int $id): void
    {
        $this->authorizeView();

        /**
         * Tenant-safe: il documento deve essere visibile nel perimetro dell'utente corrente.
         * Se non lo è → 404 (non leakiamo l'esistenza dell'ID).
         */
        $doc = $this->scopedDocsBaseQuery()
            ->where('vehicle_documents.id', $id)
            ->firstOrFail();

        $this->editingVehicleArchived = method_exists($doc->vehicle, 'trashed') && $doc->vehicle->trashed();

        $this->editingId = $doc->id;
        $this->form = [
            'vehicle_id'  => $doc->vehicle_id,
            'type'        => $doc->type,
            'number'      => $doc->number,
            'expiry_date' => $doc->expiry_date ? Carbon::parse($doc->expiry_date)->toDateString() : null,
        ];
        $this->drawerOpen = true;

        if (!$this->userCanUpdate()) {
            $this->dispatch('toast', ['type' => 'info', 'message' => 'Modalità sola lettura.']);
        }
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->editingId = null;
        $this->editingVehicleArchived = null;
        $this->resetValidation();
    }

    /** Crea/Aggiorna (admin crea; renter può aggiornare) */
    public function save(): void
    {
        $this->authorizeView();
        $this->validate();

        // Veicolo non deve essere archiviato
        $vehicle = Vehicle::withTrashed()->findOrFail($this->form['vehicle_id']);
        if (method_exists($vehicle, 'trashed') && $vehicle->trashed()) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Veicolo archiviato: non puoi modificare i documenti.']);
            return;
        }

        // Se renter → può solo UPDATE e solo per veicoli assegnati *ora*
        if ($this->editingId) {
            /**
             * Tenant-safe:
             * - Il documento deve essere visibile nel perimetro dell'utente (admin vs renter).
             * - Se l'ID non è visibile → 404 (evita leak dell'esistenza dell'ID).
             */
            $doc = $this->scopedDocsBaseQuery()
                ->where('vehicle_documents.id', $this->editingId)
                ->firstOrFail();

            /**
             * Sicurezza: vehicle_id NON deve mai essere cambiabile in update.
             * Anche se il campo è disabled in UI, può essere manomesso via request.
             * Quindi forziamo il vehicle_id reale dal record DB.
             */
            $this->form['vehicle_id'] = (int) $doc->vehicle_id;

            // Veicolo (anche archiviato) già caricato nello scope; se manca, recupero safe.
            $vehicle = $doc->vehicle ?: Vehicle::withTrashed()->findOrFail($doc->vehicle_id);

            /**
             * Veicolo non deve essere archiviato:
             * - la UI già disabilita, ma qui è il vero guardrail.
             */
            if (method_exists($vehicle, 'trashed') && $vehicle->trashed()) {
                $this->dispatch('toast', ['type' => 'warning', 'message' => 'Veicolo archiviato: non puoi modificare i documenti.']);
                return;
            }

            /**
             * Permesso update:
             * - admin-like: ok
             * - renter: ok solo se ha update/manage (già gestito da userCanUpdate).
             */
            if (!$this->userCanUpdate()) {
                $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per aggiornare.']);
                return;
            }

            /**
             * Doppio controllo per renter:
             * - Lo scope già limita ai veicoli assegnati *ora*,
             *   ma questa check rende esplicita la regola di business e protegge da future modifiche allo scope.
             */
            if ($this->isRenter() && !$this->vehicleAssignedToRenterNow($vehicle->id)) {
                $this->dispatch('toast', ['type' => 'warning', 'message' => 'Documento non appartenente alla tua flotta attuale.']);
                return;
            }

            // Update campi consentiti
            $doc->update([
                'type'        => $this->form['type'],
                'number'      => $this->form['number'],
                'expiry_date' => $this->form['expiry_date'],
            ]);

            $msg = 'Documento aggiornato.';
        } else {
            // CREATE: solo admin (o chi ha create/manage)
            if (!Auth::user()->can('vehicle_documents.create') && !Auth::user()->can('vehicle_documents.manage')) {
                $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per creare documenti.']);
                return;
            }
            VehicleDocument::create($this->form);
            $msg = 'Documento creato.';
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => $msg]);
        $this->closeDrawer();
    }

    /** Elimina: solo chi ha delete/manage e non su veicoli archiviati */
    public function delete(int $id): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.delete') && !Auth::user()->can('vehicle_documents.manage')) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per eliminare documenti.']);
            return;
        }

        /**
         * Tenant-safe: il documento deve essere visibile nel perimetro dell'utente corrente.
         * Se non lo è → 404 (non leakiamo l'esistenza dell'ID).
         */
        $doc = $this->scopedDocsBaseQuery()
            ->where('vehicle_documents.id', $id)
            ->firstOrFail();

        if ($doc->vehicle && method_exists($doc->vehicle, 'trashed') && $doc->vehicle->trashed()) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Veicolo archiviato: non puoi eliminare documenti.']);
            return;
        }

        $doc->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Documento eliminato.']);
        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /** Rinnovo bulk: richiede permesso update (renter OK), esclude veicoli non assegnati/archiviati */
    public function bulkRenew(): void
    {
        $this->authorizeView();

        if (!$this->userCanUpdate()) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per modificare documenti.']);
            return;
        }
        if (!$this->bulkRenewDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->bulkRenewDate)) {
            $this->dispatch('toast', ['type' => 'info', 'message' => 'Specificare una data valida (YYYY-MM-DD).']);
            return;
        }
        if (empty($this->selected)) {
            $this->dispatch('toast', ['type' => 'info', 'message' => 'Nessuna riga selezionata.']);
            return;
        }

        // Limita alle righe realmente modificabili nel contesto attuale
        $idsQuery = VehicleDocument::query()
            ->select('vehicle_documents.id')
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_documents.vehicle_id')
            ->when(!$this->showArchived, fn($q) => $q->whereNull('vehicles.deleted_at'))
            ->whereIn('vehicle_documents.id', $this->selected);

        // Se renter → solo veicoli assegnati ora
        if ($this->isRenter()) {
            $idsQuery->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('vehicle_assignments as va')
                    ->whereColumn('va.vehicle_id', 'vehicles.id')
                    ->where('va.renter_org_id', Auth::user()->organization_id)
                    ->where('va.status', 'active')
                    ->where('va.start_at', '<=', now())
                    ->where(function ($q) { $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now()); });
            });
        }

        $ids = $idsQuery->pluck('vehicle_documents.id')->all();

        if (empty($ids)) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Nessuna riga modificabile con il tuo profilo.']);
            return;
        }

        VehicleDocument::whereIn('id', $ids)->update(['expiry_date' => $this->bulkRenewDate]);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Rinnovo completato.']);
        $this->selected = [];
        $this->bulkRenewDate = null;
    }

    /** Master checkbox: seleziona/deseleziona tutti gli ID della pagina corrente */
    public function toggleSelectPage(bool $checked): void
    {
        $ids = (clone $this->query())
            ->forPage($this->page ?? 1, $this->perPage)
            ->pluck('vehicle_documents.id')
            ->all();

        if (empty($ids)) return;

        if ($checked) {
            $this->selected = array_values(array_unique(array_merge($this->selected, $ids)));
        } else {
            $this->selected = array_values(array_diff($this->selected, $ids));
        }
    }

    // ---------------- Query / auth helpers ----------------

    protected function authorizeView(): void
    {
        if (!Auth::user()->can('vehicle_documents.viewAny')) abort(403);
    }

    /**
     * Query base tenant-safe per singoli record:
     * - Applica SOLO i vincoli di visibilità (admin vs renter + archiviati),
     *   senza dipendere dai filtri correnti (search/type/state/...).
     * - Serve per evitare IDOR: accesso a record per ID fuori dal proprio perimetro.
     */
    protected function scopedDocsBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $q = VehicleDocument::query()
            ->select('vehicle_documents.*')
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_documents.vehicle_id')
            ->with([
                /**
                 * Carico il veicolo anche se archiviato per poter:
                 * - disabilitare editing su veicoli soft-deleted
                 * - mostrare dati coerenti nel drawer
                 */
                'vehicle' => fn ($v) => $v->withTrashed()
                    ->select(
                        'id',
                        'plate',
                        'make',
                        'model',
                        'vin',
                        'admin_organization_id',
                        'default_pickup_location_id',
                        'deleted_at'
                    ),
            ]);

        // Renter: SOLO veicoli assegnati *ora*
        if ($this->isRenter()) {
            $q->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('vehicle_assignments as va')
                    ->whereColumn('va.vehicle_id', 'vehicles.id')
                    ->where('va.renter_org_id', Auth::user()->organization_id)
                    ->where('va.status', 'active')
                    ->where('va.start_at', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now());
                    });
            });

            /**
             * Nota: per i renter non filtriamo per deleted_at del veicolo,
             * perché possono dover "vedere" documenti di veicoli assegnati anche se archiviati,
             * ma l'editing resta disabilitato (gestito altrove).
             */
        } else {
            // Admin-like: mostra archiviati solo se flag attivo (coerenza con lista)
            if (!$this->showArchived) {
                $q->whereNull('vehicles.deleted_at');
            }
        }

        return $q;
    }

    /**
     * Utente appartiene a un'organizzazione di tipo renter?
     * Nota di sicurezza:
     * - Se l'utente ha permesso "manage", lo trattiamo come admin-like (no scoping renter).
     * - Questo evita casi in cui l'org non è valorizzata/caricata e il renter finisce per vedere tutto.
     */
    protected function isRenter(): bool
    {
        // Admin-like: può vedere tutto, quindi non lo consideriamo renter.
        if (Auth::user()->can('vehicle_documents.manage')) {
            return false;
        }

        $org = Auth::user()->organization;

        return $org && method_exists($org, 'isRenter')
            ? $org->isRenter()
            : ($org?->type === 'renter');
    }

    /** Verifica se il veicolo è assegnato *ora* al renter corrente */
    protected function vehicleAssignedToRenterNow(int $vehicleId): bool
    {
        return \DB::table('vehicle_assignments as va')
            ->where('va.vehicle_id', $vehicleId)
            ->where('va.renter_org_id', Auth::user()->organization_id)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(function ($q) { $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now()); })
            ->exists();
    }

    /** L'utente può aggiornare documenti? (update o manage) */
    protected function userCanUpdate(): bool
    {
        return Auth::user()->can('vehicle_documents.update') || Auth::user()->can('vehicle_documents.manage');
    }

    /** Costruisce la query con scoping renter (se applicabile) */
    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        $today = Carbon::now()->startOfDay();

        $q = VehicleDocument::query()
            ->select('vehicle_documents.*')
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_documents.vehicle_id')
            ->with([
                'vehicle' => fn ($v) => $v->withTrashed()
                    ->select('id','plate','make','model','vin','admin_organization_id','default_pickup_location_id','deleted_at')
                    ->with(['adminOrganization:id,name', 'defaultPickupLocation:id,name']),
            ]);

        // Admin: può vedere anche archiviati (se flag); Renter: sempre scoping per assegnazioni attive
        if ($this->isRenter()) {
            $q->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('vehicle_assignments as va')
                    ->whereColumn('va.vehicle_id', 'vehicles.id')
                    ->where('va.renter_org_id', Auth::user()->organization_id)
                    ->where('va.status', 'active')
                    ->where('va.start_at', '<=', now())
                    ->where(function ($q) { $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now()); });
            });
            // per i renter, ignoriamo 'Mostra archiviati': possono vedere i doc dei veicoli assegnati a prescindere, ma
            // se il veicolo è archiviato non consentiamo modifiche (gestito altrove).
        } else {
            if (!$this->showArchived) $q->whereNull('vehicles.deleted_at');
        }

        // Ricerca libera
        if ($this->search) {
            $s = trim($this->search);
            $q->where(function ($w) use ($s) {
                $w->where('vehicle_documents.number', 'like', "%{$s}%")
                  ->orWhere('vehicles.plate', 'like', "%{$s}%")
                  ->orWhere('vehicles.vin', 'like', "%{$s}%");
            });
        }

        // Filtri
        if ($this->type && in_array($this->type, $this->allowedTypes, true)) $q->where('vehicle_documents.type', $this->type);
        if ($this->vehicleId)    $q->where('vehicles.id', $this->vehicleId);
        if ($this->orgId && Auth::user()->can('vehicle_documents.manage')) {
            $q->where('vehicles.admin_organization_id', $this->orgId);
        }
        if ($this->locId)        $q->where('vehicles.default_pickup_location_id', $this->locId);
        if ($this->from)         $q->whereDate('vehicle_documents.expiry_date', '>=', $this->from);
        if ($this->to)           $q->whereDate('vehicle_documents.expiry_date', '<=', $this->to);

        // Stato scadenza
        if ($this->state === 'expired') {
            $q->whereNotNull('vehicle_documents.expiry_date')->whereDate('vehicle_documents.expiry_date', '<', $today->toDateString());
        } elseif ($this->state === 'soon30') {
            $q->whereNotNull('vehicle_documents.expiry_date')->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()]);
        } elseif ($this->state === 'soon60') {
            $q->whereNotNull('vehicle_documents.expiry_date')->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()]);
        } elseif ($this->state === 'ok') {
            $q->whereNotNull('vehicle_documents.expiry_date')->whereDate('vehicle_documents.expiry_date', '>', $today->copy()->addDays(60)->toDateString());
        } elseif ($this->state === 'no_date') {
            $q->whereNull('vehicle_documents.expiry_date');
        }

        // Ordinamento
        $sort = in_array($this->sort, $this->allowedSort, true) ? $this->sort : 'expiry_date';
        $dir  = in_array($this->dir, ['asc','desc'], true) ? $this->dir : 'asc';
        if ($sort === 'vehicle.plate') {
            $q->orderBy('vehicles.plate', $dir)->orderBy('vehicle_documents.id', 'asc');
        } else {
            $q->orderBy("vehicle_documents.{$sort}", $dir)->orderBy('vehicle_documents.id', 'asc');
        }

        return $q;
    }

    /** KPI (come prima, ma sulla query scoped) */
    protected function kpi(): array
    {
        $state = $this->state;
        $this->state = null;
        $q = $this->query();
        $this->state = $state;

        $today = Carbon::now()->startOfDay();
        $expired = (clone $q)->whereNotNull('vehicle_documents.expiry_date')->whereDate('vehicle_documents.expiry_date', '<', $today->toDateString())->count();
        $soon30  = (clone $q)->whereNotNull('vehicle_documents.expiry_date')->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->count();
        $soon60  = (clone $q)->whereNotNull('vehicle_documents.expiry_date')->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])->count();
        $noDate  = (clone $q)->whereNull('vehicle_documents.expiry_date')->count();
        $total   = (clone $q)->count();

        return compact('expired','soon30','soon60','noDate','total');
    }

    public function render()
    {
        $this->authorizeView();

        /** @var LengthAwarePaginator $docs */
        $docs = $this->query()->paginate($this->perPage);

        $kpi   = $this->kpi();
        $orgs  = Organization::query()->select('id','name')->orderBy('name')->get();
        $locs  = Location::query()->select('id','name')->orderBy('name')->get();

        // Flag permessi per la view (no rinomina variabili già esistenti)
        $canManage = Auth::user()->can('vehicle_documents.manage');
        $canCreate = $canManage || Auth::user()->can('vehicle_documents.create');
        $canUpdate = $canManage || Auth::user()->can('vehicle_documents.update');
        $canDelete = $canManage || Auth::user()->can('vehicle_documents.delete');

        return view('livewire.documents.index', compact(
            'docs','kpi','orgs','locs','canManage','canCreate','canUpdate','canDelete'
        ) + ['docLabels' => $this->docLabels]);
    }
}
