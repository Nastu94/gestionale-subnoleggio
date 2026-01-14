<?php

namespace App\Policies;

use App\Models\{User, Vehicle, Location};
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

    /**
     * Permesso GRANULARE: caricare foto veicolo (collection vehicle_photos).
     * - Richiede permesso 'media.upload'.
     * - Admin: basta il permesso.
     * - Renter: permesso + veicolo assegnato "ora".
     * - Vietato su veicolo archiviato (soft delete).
     */
    public function uploadPhoto(User $user, Vehicle $vehicle): bool
    {
        // Permesso base per upload media
        if (! $user->can('media.upload')) {
            return false;
        }

        // Veicolo archiviato: niente upload
        if (method_exists($vehicle, 'trashed') && $vehicle->trashed()) {
            return false;
        }

        // Renter: deve essere assegnato ora
        if ($user->organization->isRenter()) {
            return $this->vehicleAssignedToRenterNow($vehicle->id, (int) $user->organization_id);
        }

        // Admin: ok
        return true;
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
     * L'utente può assegnare il veicolo a una delle PROPRIE sedi?
     * - richiede permesso 'vehicles.assign_location'
     * - se renter: il veicolo deve risultare assegnato al suo tenant ORA
     *              e la location deve appartenere allo stesso tenant
     * - per admin: basta il permesso (che hanno già via seeder)
     */
    public function assignBaseLocation(User $user, Vehicle $vehicle, Location $location): bool
    {
        if (! $user->can('vehicles.assign_location')) {
            return false;
        }

        // Renter: vincoli di tenancy
        if ($user->organization && method_exists($user->organization, 'isRenter') && $user->organization->isRenter()) {
            if ((int) $location->organization_id !== (int) $user->organization_id) {
                return false;
            }
            return $this->vehicleAssignedToRenterNow($vehicle->id, (int) $user->organization_id);
        }

        // Admin: hanno già tutti i permessi; consentiamo
        return true;
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
