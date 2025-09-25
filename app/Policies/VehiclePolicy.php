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

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.delete');
    }

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
