<?php

namespace App\Policies;

use App\Models\{User, Vehicle};
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;

class VehiclePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('vehicles.viewAny');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        if ($user->organization->isRenter()) {
            return $this->vehicleAssignedToRenterNow($vehicle->id, $user->organization_id);
        }
        return $user->can('vehicles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vehicles.create');
    }

    /**
     * Update "anagrafica" (colore, VIN, posti, carburante, ecc.).
     * I renter NON passano di qui: per loro usiamo i permessi granulari sotto.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.delete');
    }

    /**
     * Permesso GRANULARE: aggiornare i KM.
     * - Admin: basta il permesso.
     * - Renter: serve permesso + veicolo assegnato "ora".
     */
    public function updateMileage(User $user, Vehicle $vehicle): bool
    {
        if ($user->can('vehicles.update_mileage')) {
            if ($user->organization->isRenter()) {
                return $this->vehicleAssignedToRenterNow($vehicle->id, $user->organization_id);
            }
            return true;
        }
        return false;
    }

    /**
     * Permesso GRANULARE: aprire/chiudere manutenzione.
     * - Admin: basta il permesso.
     * - Renter: permesso + veicolo assegnato "ora".
     */
    public function manageMaintenance(User $user, Vehicle $vehicle): bool
    {
        if ($user->can('vehicles.manage_maintenance')) {
            if ($user->organization->isRenter()) {
                return $this->vehicleAssignedToRenterNow($vehicle->id, $user->organization_id);
            }
            return true;
        }
        return false;
    }

    /**
     * Ripristino di veicolo archiviato (soft delete).
     * Tipicamente solo admin.
     */
    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.restore');
    }

    /** Utility: verifica assegnazione attiva "ora" a quell'organizzazione renter */
    private function vehicleAssignedToRenterNow(int $vehicleId, int $renterOrgId): bool
    {
        return DB::table('vehicle_assignments as va')
            ->where('va.vehicle_id', $vehicleId)
            ->where('va.renter_org_id', $renterOrgId)
            ->where('va.status', 'active')
            ->where('va.start_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('va.end_at')->orWhere('va.end_at', '>', now());
            })
            ->exists();
    }
}
