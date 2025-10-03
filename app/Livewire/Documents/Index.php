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
 * Livewire: Documents\Index
 *
 * Gestione documenti veicoli (lista + filtri + editor in drawer, senza allegati).
 * - Filtri persistiti via query string.
 * - Operazioni bulk con data di rinnovo gestita come proprietà Livewire.
 * - Seleziona/deseleziona tutti con metodo server (no Alpine), per aggiornare sempre il contatore.
 */
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination; // necessario per accedere a $this->page e gestire la paginazione

    /** Tema pagination (opzionale, tailwind di default) */
    // protected string $paginationTheme = 'tailwind';

    /* -----------------------------
     |  Filtri (persistiti in URL)
     * ----------------------------- */

    #[Url(as: 'q')]
    public ?string $search = null;

    #[Url(as: 'type')]
    public ?string $type = null;   // insurance|road_tax|inspection|registration|green_card|ztl_permit|other

    #[Url(as: 'state')]
    public ?string $state = null;  // expired|soon30|soon60|ok|no_date

    #[Url(as: 'vehicle_id')]
    public ?int $vehicleId = null;

    #[Url(as: 'org')]
    public ?int $orgId = null;

    #[Url(as: 'loc')]
    public ?int $locId = null;

    #[Url(as: 'from')]
    public ?string $from = null;   // YYYY-MM-DD

    #[Url(as: 'to')]
    public ?string $to = null;     // YYYY-MM-DD

    #[Url(as: 'sort')]
    public string $sort = 'expiry_date';

    #[Url(as: 'dir')]
    public string $dir = 'asc';

    #[Url(as: 'per_page')]
    public int $perPage = 25;

    #[Url(as: 'archived')]
    public bool $showArchived = false;

    /* -----------------------------
     |  Selezione e UI
     * ----------------------------- */

    /** ID documenti selezionati (tutte le pagine) */
    public array $selected = [];

    /** Data di rinnovo per l’azione bulk (YYYY-MM-DD) */
    public ?string $bulkRenewDate = null;

    /** Drawer editor */
    public bool $drawerOpen = false;
    public ?int $editingId = null;                // null = create
    public ?bool $editingVehicleArchived = null;  // per bloccare azioni se veicolo archiviato

    /** Form editor (no allegati) */
    public array $form = [
        'vehicle_id'  => null,
        'type'        => null,
        'number'      => null,
        'expiry_date' => null, // YYYY-MM-DD o null
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

    /** Whitelist per sort e enum type */
    protected array $allowedSort  = ['expiry_date','type','number','vehicle.plate'];
    protected array $allowedTypes = ['insurance','road_tax','inspection','registration','green_card','ztl_permit','other'];

    /** Regole form (minime, coerenti con lo schema) */
    protected function rules(): array
    {
        return [
            'form.vehicle_id'  => ['required','integer','exists:vehicles,id'],
            'form.type'        => ['required','in:'.implode(',', $this->allowedTypes)],
            'form.number'      => ['nullable','string','max:100'],
            'form.expiry_date' => ['nullable','date'],
        ];
    }

    /* -----------------------------
     |  Normalizzazioni / reset pagina
     * ----------------------------- */

    public function updated($name, $value): void
    {
        // Quando cambiano filtri/paginazione, torniamo a pagina 1 per evitare "pagine vuote"
        $filters = ['search','type','state','vehicleId','orgId','locId','from','to','perPage','showArchived','sort','dir'];
        if (in_array($name, $filters, true)) {
            $this->resetPage();
        }
    }

    public function updatedType($value): void
    {
        if ($value !== null && !in_array($value, $this->allowedTypes, true)) {
            $this->type = null;
        }
    }
    public function updatedDir($value): void
    {
        if (!in_array($value, ['asc','desc'], true)) {
            $this->dir = 'asc';
        }
    }
    public function updatedSort($value): void
    {
        if (!in_array($value, $this->allowedSort, true)) {
            $this->sort = 'expiry_date';
        }
    }

    /* -----------------------------
     |  Toolbar / editor actions
     * ----------------------------- */

    /** Apre drawer in modalità CREATE (richiede filtro vehicleId) */
    public function openCreate(): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.manage')) {
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

    /** Apre drawer in modalità EDIT su documento esistente */
    public function openEdit(int $id): void
    {
        $this->authorizeView();

        $doc = VehicleDocument::query()
            ->with(['vehicle' => fn($q) => $q->withTrashed()->select('id','deleted_at')])
            ->findOrFail($id);

        $this->editingVehicleArchived = method_exists($doc->vehicle, 'trashed') && $doc->vehicle->trashed();

        $this->editingId = $doc->id;
        $this->form = [
            'vehicle_id'  => $doc->vehicle_id,
            'type'        => $doc->type,
            'number'      => $doc->number,
            'expiry_date' => $doc->expiry_date ? Carbon::parse($doc->expiry_date)->toDateString() : null,
        ];
        $this->drawerOpen = true;

        if (!Auth::user()->can('vehicle_documents.manage')) {
            $this->dispatch('toast', ['type' => 'info', 'message' => 'Modalità sola lettura.']);
        }
    }

    /** Chiude il drawer editor */
    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->editingId = null;
        $this->editingVehicleArchived = null;
        $this->resetValidation();
    }

    /** Salva (create/update) documento. Richiede manage + veicolo non archiviato */
    public function save(): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.manage')) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per modificare documenti.']);
            return;
        }

        $vehicle = Vehicle::withTrashed()->findOrFail($this->form['vehicle_id']);
        if (method_exists($vehicle, 'trashed') && $vehicle->trashed()) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Veicolo archiviato: non puoi modificare i documenti.']);
            return;
        }

        $this->validate();

        if ($this->editingId) {
            $doc = VehicleDocument::findOrFail($this->editingId);
            $doc->update([
                'type'        => $this->form['type'],
                'number'      => $this->form['number'],
                'expiry_date' => $this->form['expiry_date'],
            ]);
            $msg = 'Documento aggiornato.';
        } else {
            VehicleDocument::create($this->form);
            $msg = 'Documento creato.';
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => $msg]);
        $this->closeDrawer();
    }

    /** Elimina singolo documento (se permesso e veicolo non archiviato) */
    public function delete(int $id): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.manage')) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Non hai i permessi per eliminare documenti.']);
            return;
        }

        $doc = VehicleDocument::query()
            ->with(['vehicle' => fn($q) => $q->withTrashed()->select('id','deleted_at')])
            ->findOrFail($id);

        if ($doc->vehicle && method_exists($doc->vehicle, 'trashed') && $doc->vehicle->trashed()) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Veicolo archiviato: non puoi eliminare documenti.']);
            return;
        }

        $doc->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Documento eliminato.']);
        $this->selected = array_values(array_diff($this->selected, [$id]));
    }

    /**
     * Rinnovo bulk: usa la proprietà $bulkRenewDate (niente Alpine).
     * - Permessi: manage
     * - Evita documenti di veicoli archiviati
     */
    public function bulkRenew(): void
    {
        $this->authorizeView();

        if (!Auth::user()->can('vehicle_documents.manage')) {
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

        $ids = VehicleDocument::query()
            ->select('vehicle_documents.id')
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_documents.vehicle_id')
            ->when(!$this->showArchived, fn($q) => $q->whereNull('vehicles.deleted_at'))
            ->whereIn('vehicle_documents.id', $this->selected)
            ->pluck('vehicle_documents.id')
            ->all();

        if (empty($ids)) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Le righe selezionate non sono modificabili.']);
            return;
        }

        VehicleDocument::whereIn('id', $ids)->update(['expiry_date' => $this->bulkRenewDate]);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Rinnovo completato.']);
        $this->selected = [];
        $this->bulkRenewDate = null;
    }

    /**
     * Seleziona o deseleziona tutti i documenti della PAGINA CORRENTE.
     * Riceve lo stato della checkbox master.
     */
    public function toggleSelectPage(bool $checked): void
    {
        // Ricava gli ID della pagina corrente usando forPage (coerente con ordinamento/filtri)
        $ids = (clone $this->query())
            ->forPage($this->page ?? 1, $this->perPage)
            ->pluck('vehicle_documents.id')
            ->all();

        if (empty($ids)) {
            return;
        }

        if ($checked) {
            // Unione (senza duplicati)
            $this->selected = array_values(array_unique(array_merge($this->selected, $ids)));
        } else {
            // Rimuovi solo quelli della pagina
            $this->selected = array_values(array_diff($this->selected, $ids));
        }
    }

    /* -----------------------------
     |  Query / autorizzazioni / render
     * ----------------------------- */

    protected function authorizeView(): void
    {
        if (!Auth::user()->can('vehicle_documents.viewAny')) {
            abort(403);
        }
    }

    /** Costruisce la query principale secondo i filtri correnti */
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

        if (!$this->showArchived) {
            $q->whereNull('vehicles.deleted_at');
        }

        if ($this->search) {
            $s = trim($this->search);
            $q->where(function ($w) use ($s) {
                $w->where('vehicle_documents.number', 'like', "%{$s}%")
                  ->orWhere('vehicles.plate', 'like', "%{$s}%")
                  ->orWhere('vehicles.vin', 'like', "%{$s}%");
            });
        }

        if ($this->type && in_array($this->type, $this->allowedTypes, true)) {
            $q->where('vehicle_documents.type', $this->type);
        }

        if ($this->vehicleId) {
            $q->where('vehicles.id', $this->vehicleId);
        }
        if ($this->orgId) {
            $q->where('vehicles.admin_organization_id', $this->orgId);
        }
        if ($this->locId) {
            $q->where('vehicles.default_pickup_location_id', $this->locId);
        }

        if ($this->from) {
            $q->whereDate('vehicle_documents.expiry_date', '>=', $this->from);
        }
        if ($this->to) {
            $q->whereDate('vehicle_documents.expiry_date', '<=', $this->to);
        }

        if ($this->state === 'expired') {
            $q->whereNotNull('vehicle_documents.expiry_date')
              ->whereDate('vehicle_documents.expiry_date', '<', $today->toDateString());
        } elseif ($this->state === 'soon30') {
            $q->whereNotNull('vehicle_documents.expiry_date')
              ->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()]);
        } elseif ($this->state === 'soon60') {
            $q->whereNotNull('vehicle_documents.expiry_date')
              ->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()]);
        } elseif ($this->state === 'ok') {
            $q->whereNotNull('vehicle_documents.expiry_date')
              ->whereDate('vehicle_documents.expiry_date', '>', $today->copy()->addDays(60)->toDateString());
        } elseif ($this->state === 'no_date') {
            $q->whereNull('vehicle_documents.expiry_date');
        }

        $sort = in_array($this->sort, $this->allowedSort, true) ? $this->sort : 'expiry_date';
        $dir  = in_array($this->dir, ['asc','desc'], true) ? $this->dir : 'asc';

        if ($sort === 'vehicle.plate') {
            $q->orderBy('vehicles.plate', $dir)->orderBy('vehicle_documents.id', 'asc');
        } else {
            $q->orderBy("vehicle_documents.{$sort}", $dir)->orderBy('vehicle_documents.id', 'asc');
        }

        return $q;
    }

    /** KPI per pill (scaduti/≤30/≤60/senza data) con filtri correnti (eccetto 'state') */
    protected function kpi(): array
    {
        $state = $this->state;
        $this->state = null;
        $q = $this->query();
        $this->state = $state;

        $today = Carbon::now()->startOfDay();
        $expired = (clone $q)->whereNotNull('vehicle_documents.expiry_date')
            ->whereDate('vehicle_documents.expiry_date', '<', $today->toDateString())->count();
        $soon30  = (clone $q)->whereNotNull('vehicle_documents.expiry_date')
            ->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->count();
        $soon60  = (clone $q)->whereNotNull('vehicle_documents.expiry_date')
            ->whereBetween('vehicle_documents.expiry_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])->count();
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
        $canManage = Auth::user()->can('vehicle_documents.manage');

        return view('livewire.documents.index', [
            'docs'       => $docs,
            'kpi'        => $kpi,
            'orgs'       => $orgs,
            'locs'       => $locs,
            'canManage'  => $canManage,
            'docLabels'  => $this->docLabels,
        ]);
    }
}
