<?php

namespace App\Policies;

use App\Models\{User, VehicleBlock};
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\DB;

class VehicleBlockPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('blocks.viewAny');
    }

    public function view(User $user, VehicleBlock $block): bool
    {
        if ($user->organization->isRenter()) {
            // Il block deve essere creato dalla sua org o su veicolo a lui affidato
            $mine = $block->organization_id === $user->organization_id;
            $assigned = $this->vehicleAssignedToRenterNow($block->vehicle_id, $user->organization_id);
            return ($mine || $assigned) && $user->can('blocks.view');
        }
        return $user->can('blocks.view');
    }

    public function create(User $user): bool
    {
        return $user->can('blocks.create');
    }

    public function update(User $user, VehicleBlock $block): bool
    {
        if ($user->organization->isRenter()) {
            return $block->organization_id === $user->organization_id && $user->can('blocks.update');
        }
        return $user->can('blocks.update');
    }

    public function delete(User $user, VehicleBlock $block): bool
    {
        if ($user->organization->isRenter()) {
            return $block->organization_id === $user->organization_id && $user->can('blocks.delete');
        }
        return $user->can('blocks.delete');
    }

    public function override(User $user, VehicleBlock $block): bool
    {
        // Solo admin (permesso dedicato)
        return $user->can('blocks.override');
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
