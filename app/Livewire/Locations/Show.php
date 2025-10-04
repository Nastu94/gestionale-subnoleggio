<?php

namespace App\Livewire\Locations;

use App\Models\{Location, Vehicle, Rental};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Show extends Component
{
    use AuthorizesRequests;

    /** @var Location */
    public Location $location;

    /** @var string|null Ricerca veicoli */
    public ?string $vehicleSearch = null;

    /** @var array<int> Selezione bulk veicoli da assegnare a questa sede */
    public array $selectedVehicleIds = [];

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->location = $location;
    }

    /**
     * Veicoli attualmente assegnati come "sede base" a questa location.
     */
    public function getAssignedVehiclesProperty()
    {
        return Vehicle::query()
            ->where('default_pickup_location_id', $this->location->id)
            ->orderBy('plate')
            ->get();
    }

    /**
     * Veicoli assegnabili a questa sede:
     * - appartengono al contesto del renter (tramite assegnazione attiva)
     * - non hanno noleggio attivo
     * - match ricerca se presente
     */
    public function getAssignableVehiclesProperty()
    {
        $user  = Auth::user();
        $orgId = (int) $user->organization_id;

        // Preferiamo operare sui veicoli "assegnati attivi" al renter corrente
        // (tabella vehicle_assignments), così un renter non vede veicoli non suoi.
        $sqlActiveAssignment = DB::table('vehicle_assignments as va')
            ->select('va.vehicle_id')
            ->where('va.renter_org_id', $orgId)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now());
            });

        $q = Vehicle::query()
            ->whereIn('id', $sqlActiveAssignment)
            // escludo quelli già su questa sede per non duplicare
            ->where(function ($w) {
                $w->whereNull('default_pickup_location_id')
                  ->orWhere('default_pickup_location_id', '!=', $this->location->id);
            });

        if ($term = $this->cleanSearch($this->vehicleSearch)) {
            $like = "%{$term}%";
            $q->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(plate) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(make) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(model) LIKE ?', [$like]);
            });
        }

        // Filtra quelli senza noleggio attivo
        $vehicles = $q->orderBy('plate')->get();

        return $vehicles->filter(fn (Vehicle $v) => !$this->hasActiveRental($v->id))->values();
    }

    public function assignSelected(): void
    {
        // Permesso: usiamo vehicles.update (il renter lo ha), non richiediamo locations.update.
        $user = Auth::user();
        if (!$user->can('vehicles.update')) {
            $this->dispatch('toast', type:'error', message:'Permesso mancante: vehicles.update');
            return;
        }

        if (empty($this->selectedVehicleIds)) {
            $this->dispatch('toast', type:'warning', message:'Seleziona almeno un veicolo');
            return;
        }

        $assigned = 0; $skipped = 0;

        DB::transaction(function () use (&$assigned, &$skipped) {
            foreach (array_unique($this->selectedVehicleIds) as $vid) {
                // Lock del veicolo per evitare race
                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->whereKey($vid)->lockForUpdate()->first();
                if (!$vehicle) { $skipped++; continue; }

                // Guard-rails: noleggio attivo → salta
                if ($this->hasActiveRental($vehicle->id)) { $skipped++; continue; }

                // Lo user deve avere un'assegnazione attiva su quel veicolo (multi-tenant safety)
                if (!$this->vehicleAssignedToCurrentRenter($vehicle->id)) { $skipped++; continue; }

                // Update sede base istantaneo
                $vehicle->default_pickup_location_id = $this->location->id;
                $vehicle->save();

                $assigned++;
            }
        });

        // Reset selezione e feedback UI
        $this->selectedVehicleIds = [];
        $msg = "{$assigned} assegnati";
        if ($skipped) { $msg .= " • {$skipped} saltati"; }
        $this->dispatch('toast', type:'success', message:$msg);
    }

    public function render()
    {
        return view('livewire.locations.show', [
            'assigned'   => $this->assigned_vehicles,
            'assignable' => $this->assignable_vehicles,
        ]);
    }

    // -----------------------------
    // Helpers (incapsulano regole)
    // -----------------------------

    /** Noleggio attivo = status in ('checked_out','in_use') e non ancora rientrato. */
    private function hasActiveRental(int $vehicleId): bool
    {
        return Rental::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', ['checked_out','in_use'])
            ->whereNull('actual_return_at')
            ->exists();
    }

    /** Veicolo assegnato davvero al renter corrente (vehicle_assignments attivo). */
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

    /** Normalizza ricerca veicoli: *→%, spazi→% e lowercase. */
    private function cleanSearch(?string $s): ?string
    {
        if (!$s) return null;
        $s = strtolower(trim($s));
        $s = str_replace('*', '%', $s);
        $s = preg_replace('/\s+/', '%', $s);
        return $s;
    }
}
