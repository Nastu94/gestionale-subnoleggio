<?php

namespace App\Livewire\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Models\VehicleMileageLog;
use App\Models\VehicleMaintenanceDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Component Livewire: Vehicles\Table
 *
 * - Elenco veicoli con ricerca/filtri/ordinamento + drawer laterale.
 * - Azioni: aggiorna km, segna/chiudi manutenzione, archivia (soft delete), ripristina, bulk selezionati.
 * - Aderente a Laravel 12 / PHP 8; rispetta Policy/Permessi esistenti (Spatie + Policy).
 *
 * NB: non rinominiamo colonne/relazioni, ci adattiamo allo schema già presente.
 */
class Table extends Component
{
    use WithPagination;
    use AuthorizesRequests;

    /** Stato UI sincronizzato su query string (deep-link) */
    #[Url(as: 'q',        except: '')]
    public string $search = '';

    #[Url(as: 'sort',     except: 'plate')]
    public string $sortField = 'plate';

    #[Url(as: 'dir',      except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    #[Url(as: 'tech')]
    public ?string $filterTechnical = null;     // 'maintenance' | 'ok' | null

    #[Url(as: 'avail')]
    public ?string $filterAvailability = null;  // 'assigned' | 'free' | null

    #[Url(as: 'fuel')]
    public ?string $filterFuel = null;          // 'diesel','petrol','hybrid','electric','lpg','cng' | null

    /** Toggle: includi soft-deleted in lista (per ripristino) */
    public bool $showArchived = false;

    /** Selezioni riga (pagina corrente) */
    public array $selected = [];
    public bool $selectPage = false;

    /** Drawer */
    public ?int $drawerVehicleId = null;

    /** KPI header */
    public array $kpi = [
        'available'   => 0,
        'assigned'    => 0,
        'maintenance' => 0,
        'expiring'    => 0,
    ];

    /** Modale manutenzione */
    public ?int $maintenanceModalVehicleId = null;
    public string $maintenanceWorkshopInput = '';
    public ?string $maintenanceOpenNotes = null;

    public ?float $maintenanceCloseCost = null;
    public ?string $maintenanceCloseNotes = null;

    /** Sanitizzazione input iniziali */
    public function mount(): void
    {
        $this->perPage = max(5, min(200, (int) $this->perPage));
        $allowedSort = ['plate','make','model','year','mileage_current'];
        if (!in_array($this->sortField, $allowedSort, true)) {
            $this->sortField = 'plate';
        }
        $this->sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Query base limitata dal ruolo:
     * - Admin: tutto.
     * - Renter: solo veicoli assegnati alla propria organization ora.
     */
    protected function baseQuery()
    {
        $now = Carbon::now();

        $q = Vehicle::query()
            ->when($this->showArchived, fn ($qq) => $qq->withTrashed()) // include archiviati quando richiesto
            ->with([
                'adminOrganization:id,name',
                'defaultPickupLocation:id,name',
            ])
            // Flag calcolati a DB per tabella
            ->withExists(['assignments as is_assigned' => function ($sub) use ($now) {
                $sub->where('status', 'active')
                    ->where('start_at', '<=', $now)
                    ->where(function ($w) use ($now) {
                        $w->whereNull('end_at')->orWhere('end_at', '>', $now);
                    });
            }])
            ->withExists(['states as is_maintenance' => function ($sub) {
                $sub->whereIn('state', ['maintenance', 'out_of_service'])
                    ->whereNull('ended_at');
            }])
            ->withMin(['documents as next_expiry_date' => function ($d) use ($now) {
                $d->whereNotNull('expiry_date')->where('expiry_date', '>=', $now->toDateString());
            }], 'expiry_date');

        // Scope renter: solo assegnati alla sua org ora
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('renter')) {
            $orgId = $user->organization_id; // già in schema
            $q->whereHas('assignments', function ($a) use ($orgId, $now) {
                $a->where('status', 'active')
                  ->where('renter_org_id', $orgId)
                  ->where('start_at', '<=', $now)
                  ->where(function ($w) use ($now) {
                      $w->whereNull('end_at')->orWhere('end_at', '>', $now);
                  });
            });
        }

        return $q;
    }

    /** Applica ricerca/filtri e ordinamento (whitelist) */
    protected function applyFilters($q)
    {
        $now = Carbon::now();

        // Ricerca semplice con wildcard su spazi e '*'
        if (trim($this->search) !== '') {
            $term = mb_strtolower(trim($this->search));
            $term = str_replace('*', '%', $term);
            $term = preg_replace('/\s+/', '%', $term);
            $like = "%{$term}%";

            $q->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(plate) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(vin) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(make) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(model) LIKE ?', [$like]);
            });
        }

        // Filtro stato tecnico
        if ($this->filterTechnical === 'maintenance') {
            $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))->from('vehicle_states as vs')
                   ->whereColumn('vs.vehicle_id', 'vehicles.id')
                   ->whereIn('vs.state', ['maintenance','out_of_service'])
                   ->whereNull('vs.ended_at');
            });
        } elseif ($this->filterTechnical === 'ok') {
            $q->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('vehicle_states as vs')
                   ->whereColumn('vs.vehicle_id', 'vehicles.id')
                   ->whereIn('vs.state', ['maintenance','out_of_service'])
                   ->whereNull('vs.ended_at');
            });
        }

        // Filtro disponibilità commerciale
        if ($this->filterAvailability === 'assigned') {
            $q->whereExists(function ($sub) use ($now) {
                $sub->select(DB::raw(1))->from('vehicle_assignments as va')
                   ->whereColumn('va.vehicle_id', 'vehicles.id')
                   ->where('va.status', 'active')
                   ->where('va.start_at', '<=', $now)
                   ->where(function ($w) use ($now) {
                       $w->whereNull('va.end_at')->orWhere('va.end_at', '>', $now);
                   });
            });
        } elseif ($this->filterAvailability === 'free') {
            $q->whereNotExists(function ($sub) use ($now) {
                $sub->select(DB::raw(1))->from('vehicle_assignments as va')
                   ->whereColumn('va.vehicle_id', 'vehicles.id')
                   ->where('va.status', 'active')
                   ->where('va.start_at', '<=', $now)
                   ->where(function ($w) use ($now) {
                       $w->whereNull('va.end_at')->orWhere('va.end_at', '>', $now);
                   });
            });
        }

        // Filtro carburante
        if (!empty($this->filterFuel)) {
            $q->where('fuel_type', $this->filterFuel);
        }

        // Ordinamento sicuro
        $q->orderBy($this->sortField, $this->sortDirection)
          ->orderBy('id', 'asc'); // tiebreaker stabile

        return $q;
    }

    /** KPI (scopo ruolo, no filtri UI) */
    protected function computeKpi(): void
    {
        $base = $this->baseQuery()->toBase();
        $now  = Carbon::now();

        $available = DB::table('vehicles')
            ->whereIn('vehicles.id', $base->select('vehicles.id'))
            ->whereNotExists(function ($s) use ($now) {
                $s->select(DB::raw(1))->from('vehicle_assignments as va')
                  ->whereColumn('va.vehicle_id', 'vehicles.id')
                  ->where('va.status', 'active')
                  ->where('va.start_at', '<=', $now)
                  ->where(function ($w) use ($now) {
                      $w->whereNull('va.end_at')->orWhere('va.end_at', '>', $now);
                  });
            })
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))->from('vehicle_states as vs')
                  ->whereColumn('vs.vehicle_id', 'vehicles.id')
                  ->whereIn('vs.state', ['maintenance','out_of_service'])
                  ->whereNull('vs.ended_at');
            })
            ->count();

        $assigned = DB::table('vehicles')
            ->whereIn('vehicles.id', $base->select('vehicles.id'))
            ->whereExists(function ($s) use ($now) {
                $s->select(DB::raw(1))->from('vehicle_assignments as va')
                  ->whereColumn('va.vehicle_id', 'vehicles.id')
                  ->where('va.status', 'active')
                  ->where('va.start_at', '<=', $now)
                  ->where(function ($w) use ($now) {
                      $w->whereNull('va.end_at')->orWhere('va.end_at', '>', $now);
                  });
            })
            ->count();

        $maintenance = DB::table('vehicles')
            ->whereIn('vehicles.id', $base->select('vehicles.id'))
            ->whereExists(function ($s) {
                $s->select(DB::raw(1))->from('vehicle_states as vs')
                  ->whereColumn('vs.vehicle_id', 'vehicles.id')
                  ->whereIn('vs.state', ['maintenance','out_of_service'])
                  ->whereNull('vs.ended_at');
            })
            ->count();

        $expiring = DB::table('vehicle_documents as vd')
            ->joinSub($base->select('vehicles.id'), 'v', 'v.id', '=', 'vd.vehicle_id')
            ->whereNotNull('vd.expiry_date')
            ->whereBetween('vd.expiry_date', [Carbon::now()->toDateString(), Carbon::now()->addDays(60)->toDateString()])
            ->distinct('vd.vehicle_id')
            ->count('vd.vehicle_id');

        $this->kpi = compact('available','assigned','maintenance','expiring');
    }

    /** Sorting toggle asc/desc con whitelist */
    public function sortBy(string $field): void
    {
        $allowed = ['plate','make','model','year','mileage_current'];
        if (!in_array($field, $allowed, true)) return;

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    /** Seleziona/Deseleziona tutti gli ID visibili in pagina */
    public function toggleSelectAllOnPage(array $idsOnPage): void
    {
        $this->selectPage = !$this->selectPage;
        $this->selected   = $this->selectPage ? $idsOnPage : [];
    }

    /** Apertura/chiusura drawer */
    public function openDrawer(int $vehicleId): void { $this->drawerVehicleId = $vehicleId; }
    public function closeDrawer(): void { $this->drawerVehicleId = null; }

    /**
     * Aggiorna chilometraggio singolo veicolo (anti-regressione).
     * @param int $vehicleId ID veicolo
     * @param int $mileage   valore assoluto corrente
     */
    public function updateMileage(int $vehicleId, int $mileage): void
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($vehicleId);
        $this->authorize('updateMileage', $vehicle);

        if ($vehicle->trashed()) {
            $this->addError('mileage', 'Veicolo archiviato: ripristina prima di aggiornare i km.');
            return;
        }

        // CATTURA L'OLD PRIMA DI MODIFICARE
        $old = (int) $vehicle->mileage_current;

        if ($mileage < $old) {
            $this->addError('mileage', 'Il chilometraggio non può diminuire.');
            return;
        }

        $vehicle->mileage_current = $mileage;
        $vehicle->save();

        // LOG con old corretto
        VehicleMileageLog::create([
            'vehicle_id'  => $vehicle->id,
            'mileage_old' => $old,
            'mileage_new' => (int) $mileage,
            'changed_by'  => auth()->id(),
            'source'      => 'manual',
            'notes'       => 'Aggiornamento da tabella veicoli',
            'changed_at'  => now(),
        ]);

        // ✅ Evento toast con payload compatibile Livewire v3
        $this->dispatch('toast', [
            'type'    => 'success',
            'message' => 'Chilometraggio aggiornato.',
        ]);
    }

    /** Apre stato tecnico "manutenzione" se non è già aperto (singolo) */
    public function setMaintenance(int $vehicleId): void
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($vehicleId);
        $this->authorize('manageMaintenance', $vehicle); // <— nuovo check

        if ($vehicle->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        $exists = VehicleState::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('state', ['maintenance','out_of_service'])
            ->whereNull('ended_at')
            ->exists();

        if (!$exists) {
            VehicleState::create([
                'vehicle_id' => $vehicle->id,
                'state'      => 'maintenance',
                'started_at' => now(),
                'ended_at'   => null,
                'reason'     => 'Impostato dalla gestione veicoli',
                'created_by' => auth()->id(),
            ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Veicolo impostato in manutenzione.');
    }

    /** Chiude eventuale stato tecnico aperto (singolo) */
    public function clearTechnicalState(int $vehicleId): void
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($vehicleId);
        $this->authorize('manageMaintenance', $vehicle); // <— nuovo check

        if ($vehicle->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        VehicleState::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('state', ['maintenance','out_of_service'])
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $this->dispatch('toast', type: 'success', message: 'Stato tecnico chiuso.');
    }

    /** Conferma apertura manutenzione */
    public function confirmOpenMaintenance(int $vehicleId, string $workshop, ?string $notes = null): void
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($vehicleId);
        $this->authorize('manageMaintenance', $vehicle);

        if ($vehicle->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        $already = VehicleState::where('vehicle_id', $vehicle->id)
            ->whereIn('state', ['maintenance','out_of_service'])
            ->whereNull('ended_at')
            ->exists();

        if ($already) {
            $this->dispatch('toast', type: 'info', message: 'Manutenzione già aperta.');
            return;
        }

        $state = VehicleState::create([
            'vehicle_id' => $vehicle->id,
            'state'      => 'maintenance',
            'started_at' => now(),
            'ended_at'   => null,
            'reason'     => 'Apertura da tabella veicoli',
            'created_by' => auth()->id(),
        ]);

        VehicleMaintenanceDetail::create([
            'vehicle_state_id' => $state->id,
            'workshop'         => trim($workshop) !== '' ? trim($workshop) : 'N/D',
            'notes'            => $notes,
            'currency'         => 'EUR',
        ]);

        // pulizia input modal
        $this->maintenanceModalVehicleId = null;
        $this->maintenanceWorkshopInput = '';
        $this->maintenanceOpenNotes = null;

        $this->dispatch('toast', type: 'success', message: 'Manutenzione aperta.');
    }

    /** Conferma chiusura manutenzione */
    public function confirmCloseMaintenance(int $vehicleId, ?float $costEuro = null, ?string $notes = null): void
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($vehicleId);
        $this->authorize('manageMaintenance', $vehicle);

        if ($vehicle->trashed()) {
            $this->dispatch('toast', type: 'warning', message: 'Veicolo archiviato: ripristina prima di modificare lo stato.');
            return;
        }

        $state = VehicleState::where('vehicle_id', $vehicle->id)
            ->whereIn('state', ['maintenance'])
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (!$state) {
            $this->dispatch('toast', type: 'error', message: 'Nessuna manutenzione aperta.');
            return;
        }

        $state->update(['ended_at' => now()]);

        $detail = VehicleMaintenanceDetail::firstOrCreate(
            ['vehicle_state_id' => $state->id],
            ['workshop' => 'N/D', 'currency' => 'EUR']
        );

        if ($costEuro !== null) {
            $detail->cost_cents = (int) round($costEuro * 100);
        }
        if ($notes) {
            // append o sovrascrivi; qui append per tenere traccia
            $detail->notes = trim($detail->notes ? ($detail->notes." — ".$notes) : $notes);
        }
        $detail->save();

        // pulizia input modal
        $this->maintenanceModalVehicleId = null;
        $this->maintenanceCloseCost = null;
        $this->maintenanceCloseNotes = null;

        $this->dispatch('toast', type: 'success', message: 'Manutenzione chiusa.');
    }

    /** Archivia (soft delete) singolo veicolo */
    public function archive(int $vehicleId): void
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        $this->authorize('delete', $vehicle);
        $vehicle->delete();

        $this->selected = [];
        $this->selectPage = false;

        $this->dispatch('toast', type: 'success', message: 'Veicolo archiviato.');
    }

    /** Ripristina veicolo archiviato */
    public function restore(int $vehicleId): void
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($vehicleId);
        $this->authorize('restore', $vehicle); // <— usa policy restore

        if ($vehicle->trashed()) {
            $vehicle->restore();
            $this->dispatch('toast', type: 'success', message: 'Veicolo ripristinato.');
        }
    }

    /** BULK: segna in manutenzione tutti i selezionati */
    public function setMaintenanceSelected(): void
    {
        if (empty($this->selected)) return;

        $now = now();
        foreach (Vehicle::withTrashed()->whereIn('id', $this->selected)->get() as $v) {
            $this->authorize('manageMaintenance', $v); // <— nuovo check
            if ($v->trashed()) continue;

            $exists = VehicleState::where('vehicle_id', $v->id)
                ->whereIn('state', ['maintenance','out_of_service'])
                ->whereNull('ended_at')
                ->exists();

            if (!$exists) {
                $st = VehicleState::create([
                    'vehicle_id' => $v->id,
                    'state'      => 'maintenance',
                    'started_at' => $now,
                    'ended_at'   => null,
                    'reason'     => 'Bulk manutenzione (selezionati)',
                    'created_by' => auth()->id(),
                ]);

                VehicleMaintenanceDetail::create([
                    'vehicle_state_id' => $st->id,
                    'workshop'         => 'N/D',
                    'notes'            => 'Apertura dalla tabella veicoli',
                    'currency'         => 'EUR',
                ]);
            }
        }

        $this->selected = [];
        $this->selectPage = false;

        $this->dispatch('toast', type: 'success', message: 'Manutenzione impostata sui selezionati.');
    }

    /** BULK: archivia tutti i selezionati */
    public function archiveSelected(): void
    {
        if (empty($this->selected)) return;

        foreach (Vehicle::whereIn('id', $this->selected)->get() as $v) {
            $this->authorize('delete', $v);
            $v->delete();
        }

        $this->selected = [];
        $this->selectPage = false;

        $this->dispatch('toast', type: 'success', message: 'Veicoli archiviati (selezionati).');
    }

    /** Dati drawer (profilo + doc + stato + assegnazione corrente) */
    protected function drawerData(?int $vehicleId): array
    {
        if (!$vehicleId) return [];

        $v = Vehicle::with([
            'adminOrganization:id,name',
            'defaultPickupLocation:id,name',
            'documents'    => fn ($q) => $q->orderBy('expiry_date'),
            'states'       => fn ($q) => $q->orderByDesc('started_at'),
            'assignments'  => fn ($q) => $q->orderByDesc('start_at'),
        ])->find($vehicleId);

        if (!$v) return [];

        $currentState = DB::table('vehicle_states')
            ->where('vehicle_id', $v->id)
            ->whereNull('ended_at')
            ->value('state');

        $today = now()->toDateString();
        $limit = now()->addDays(60)->toDateString();
        $expiring = $v->documents->whereNotNull('expiry_date')
            ->filter(fn ($d) => $d->expiry_date >= $today && $d->expiry_date <= $limit)->count();
        $expired  = $v->documents->whereNotNull('expiry_date')
            ->filter(fn ($d) => $d->expiry_date < $today)->count();

        $assignedNow = DB::table('vehicle_assignments as va')
            ->leftJoin('organizations as o', 'o.id', '=', 'va.renter_org_id')
            ->select('o.id as renter_org_id', 'o.name as renter_name', 'va.id as assignment_id')
            ->where('va.vehicle_id', $v->id)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(function ($w) { $w->whereNull('va.end_at')->orWhere('va.end_at', '>', now()); })
            ->first();

        return compact('v','currentState','expiring','expired','assignedNow');
    }

    // Sanifica l'array $selected escludendo i trashed
    protected function sanitizeSelection(): void
    {
        if (empty($this->selected)) {
            return;
        }

        // Vehicle senza withTrashed() esclude già gli archiviati → li rimuove da $selected
        $this->selected = array_values(
            Vehicle::query()->whereIn('id', $this->selected)->pluck('id')->all()
        );
    }

    /** Quando mostri/nascondi gli archiviati, azzera la selezione di sicurezza */
    public function updatedShowArchived(): void
    {
        $this->selectPage = false;
        $this->selected   = [];
    }

    /** Render: dataset + ids pagina + drawer + KPI */
    public function render()
    {
        $this->computeKpi();

        $query    = $this->applyFilters($this->baseQuery());
        $vehicles = $query->paginate($this->perPage);

        // pulizia selezione (server-side guard)
        $this->sanitizeSelection();

        // ID in pagina
        $idsOnPage = $vehicles->getCollection()->pluck('id')->all();

        // Solo gli ID NON archiviati della pagina (per il "seleziona tutti")
        $idsSelectableOnPage = $vehicles->getCollection()
            ->filter(fn ($v) => !(method_exists($v, 'trashed') && $v->trashed()))
            ->pluck('id')
            ->all();
        $drawer    = $this->drawerData($this->drawerVehicleId);

        return view('livewire.vehicles.table', [
            'vehicles'  => $vehicles,
            'idsOnPage' => $idsOnPage,
            'idsSelectableOnPage' => $idsSelectableOnPage,
            'drawer'    => $drawer,
            'kpi'       => $this->kpi,
        ]);
    }
}
