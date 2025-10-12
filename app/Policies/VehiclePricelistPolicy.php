<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehiclePricelist;

class VehiclePricelistPolicy
{
    public function viewAny(User $user): bool {
        return $user->can('vehicle_pricing.viewAny');
    }

    public function view(User $user, VehiclePricelist $pricelist): bool {
        return $user->can('vehicle_pricing.view');
    }

    public function create(User $user): bool {
        return $user->can('vehicle_pricing.create');
    }

    public function update(User $user, VehiclePricelist $pricelist): bool {
        return $user->can('vehicle_pricing.update');
    }

    public function publish(User $user, VehiclePricelist $pricelist): bool {
        return $user->can('vehicle_pricing.publish');
    }

    public function archive(User $user, VehiclePricelist $pricelist): bool {
        return $user->can('vehicle_pricing.archive');
    } 

    public function delete(User $user, VehiclePricelist $pricelist): bool {
        return $user->can('vehicle_pricing.delete');
    }
}
