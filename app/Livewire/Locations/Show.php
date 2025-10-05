<?php

namespace App\Livewire\Locations;

use App\Models\{Location, Vehicle, Rental};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Livewire: Sedi ▸ Dettaglio
 *
 * - Tab "Parco veicoli": assegna veicoli come sede base (default_pickup_location_id) in modo ISTANTANEO.
 * - Vincoli:
 *   - Nessun noleggio attivo sul veicolo (status 'checked_out'/'in_use' e actual_return_at NULL).
 *   - Stesso contesto tenant: il veicolo deve essere assegnato al renter corrente (vehicle_assignments attivo).
 * - Toast per tutti i feedback (success/warning/error).
 */
class Show extends Component
{
    use AuthorizesRequests;

    /** Sede corrente */
    public Location $location;

    /** Ricerca veicoli assegnabili */
    public ?string $vehicleSearch = null;

    /** Selezione multipla per bulk-assign */
    public array $selectedVehicleIds = [];

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->location = $location;
    }

    /** Veicoli attualmente con sede base = questa location */
    public function getAssignedVehiclesProperty()
    {
        return Vehicle::query()
            ->where('default_pickup_location_id', $this->location->id)
            ->orderBy('plate')
            ->get();
    }

    /**
     * Veicoli assegnabili alla sede:
     * - devono appartenere al renter corrente (assegnazione attiva),
     * - NON avere noleggio attivo,
     * - non essere già su questa sede,
     * - match ricerca se presente.
     */
    public function getAssignableVehiclesProperty()
    {
        $user  = Auth::user();
        $orgId = (int) $user->organization_id;

        // Subquery degli ID veicolo assegnati attivamente al tenant (oggi)
        $activeAssignmentIds = DB::table('vehicle_assignments as va')
            ->select('va.vehicle_id')
            ->where('va.renter_org_id', $orgId)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now());
            });

        $term = $this->normalize($this->vehicleSearch);
        $like = $term ? "%{$term}%" : null;

        $q = Vehicle::query()
            ->whereIn('id', $activeAssignmentIds)
            ->where(function ($w) {
                $w->whereNull('default_pickup_location_id')
                  ->orWhere('default_pickup_location_id', '!=', $this->location->id);
            });

        if ($term) {
            $q->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(plate) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(make) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(model) LIKE ?', [$like]);
            });
        }

        // Recupero e filtro per "noleggio non attivo"
        $vehicles = $q->orderBy('plate')->get();

        return $vehicles->filter(fn (Vehicle $v) => !$this->hasActiveRental($v->id))->values();
    }

    /**
     * Assegna i veicoli selezionati a questa sede come "base".
     * - Richiede permesso 'vehicles.update'.
     * - Blocca quelli con noleggio attivo o non assegnati al tenant.
     * - Update atomico con lock per evitare race.
     */
    public function assignSelected(): void
    {
        $user = auth()->user();

        // 1) Permesso generale: vehicles.assign_location (no più vehicles.update)
        if (! $user->can('vehicles.assign_location')) {
            $this->dispatch('toast', type:'error', message:'Permesso mancante: vehicles.assign_location');
            return;
        }

        if (empty($this->selectedVehicleIds)) {
            $this->dispatch('toast', type:'warning', message:'Seleziona almeno un veicolo.');
            return;
        }

        $assigned = 0; $skipped = 0;

        \DB::transaction(function () use (&$assigned, &$skipped) {
            foreach (array_unique($this->selectedVehicleIds) as $vid) {
                /** @var \App\Models\Vehicle|null $vehicle */
                $vehicle = \App\Models\Vehicle::query()->whereKey($vid)->lockForUpdate()->first();
                if (! $vehicle) { $skipped++; continue; }

                // 2) Autorizzazione per-veicolo con POLICY (VehiclePolicy@assignBaseLocation)
                if (! Gate::allows('assignBaseLocation', [$vehicle, $this->location])) {
                    $skipped++; continue;
                }

                // 3) Business guard-rails: NO noleggio attivo
                if ($this->hasActiveRental($vehicle->id)) { $skipped++; continue; }

                // 4) Aggiorna sede base istantaneamente
                $vehicle->default_pickup_location_id = $this->location->id;
                $vehicle->save();

                $assigned++;
            }
        });

        $this->selectedVehicleIds = [];
        $msg = "{$assigned} veicolo/i assegnato/i";
        if ($skipped) { $msg .= " • {$skipped} saltato/i"; }

        $this->dispatch('toast', type:'success', message:$msg);
    }

    /**
     * Statistiche sintetiche per la sede corrente.
     * - vehicles_count: veicoli con sede base = location
     * - active_pickup_here: noleggi attivi con pickup in questa location
     * - active_return_here: noleggi attivi con drop-off in questa location
     * - planned_pickups_today: ritiri pianificati OGGI (non ancora ritirati) in questa location
     * - planned_returns_today: rientri pianificati OGGI (non ancora rientrati) in questa location
     */
    public function getStatsProperty(): array
    {
        $locId = (int) $this->location->id;

        // Conteggio veicoli con sede base qui
        $vehiclesCount = Vehicle::query()
            ->where('default_pickup_location_id', $locId)
            ->count();

        // Stati “attivi” a sistema
        $activeStatuses = ['checked_out', 'in_use'];

        $activePickupHere = Rental::query()
            ->whereIn('status', $activeStatuses)
            ->where('pickup_location_id', $locId)
            ->count();

        $activeReturnHere = Rental::query()
            ->whereIn('status', $activeStatuses)
            ->where('return_location_id', $locId)
            ->count();

        // Finestra temporale “oggi” (timezone dell’app)
        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        // Escludo cancellati / no_show; conto solo quelli non ancora eseguiti
        $plannedPickupsToday = Rental::query()
            ->whereBetween('planned_pickup_at', [$start, $end])
            ->where('pickup_location_id', $locId)
            ->whereNull('actual_pickup_at')                 // ancora da ritirare
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();

        $plannedReturnsToday = Rental::query()
            ->whereBetween('planned_return_at', [$start, $end])
            ->where('return_location_id', $locId)
            ->whereNull('actual_return_at')                 // ancora da rientrare
            ->whereNotIn('status', ['cancelled', 'no_show', 'checked_in']) // già chiusi fuori
            ->count();

        return [
            'vehicles_count'         => $vehiclesCount,
            'active_pickup_here'     => $activePickupHere,
            'active_return_here'     => $activeReturnHere,
            'planned_pickups_today'  => $plannedPickupsToday,
            'planned_returns_today'  => $plannedReturnsToday,
        ];
    }

    public function render()
    {
        return view('livewire.locations.show', [
            'assigned'   => $this->assigned_vehicles,
            'assignable' => $this->assignable_vehicles,
            'stats'      => $this->stats, 
        ]);
    }

    // -----------------------------
    // Helpers di dominio/regole
    // -----------------------------

    /** True se esiste un noleggio attivo per il veicolo. */
    private function hasActiveRental(int $vehicleId): bool
    {
        return Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['checked_out', 'in_use'])
            ->whereNull('actual_return_at')
            ->exists();
    }

    /** True se il veicolo è assegnato al renter corrente (assegnazione attiva). */
    private function vehicleAssignedToCurrentRenter(int $vehicleId): bool
    {
        $orgId = (int) Auth::user()->organization_id;

        return DB::table('vehicle_assignments as va')
            ->where('va.vehicle_id', $vehicleId)
            ->where('va.renter_org_id', $orgId)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now());
            })
            ->exists();
    }

    /** Normalizza la ricerca veicoli: lower, '*'→'%', spazi→'%' */
    private function normalize(?string $s): ?string
    {
        if (! $s) return null;
        $s = strtolower(trim($s));
        $s = str_replace('*', '%', $s);
        return preg_replace('/\s+/', '%', $s);
    }
}
