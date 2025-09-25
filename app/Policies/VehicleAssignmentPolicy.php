<?php

namespace App\Policies;

use App\Models\{User, VehicleAssignment};
use Illuminate\Auth\Access\HandlesAuthorization;

class VehicleAssignmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('assignments.viewAny');
    }

    public function view(User $user, VehicleAssignment $va): bool
    {
        if ($user->organization->isRenter()) {
            return $va->renter_org_id === $user->organization_id && $user->can('assignments.view');
        }
        return $user->can('assignments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('assignments.create'); // tipicamente solo admin
    }
    public function update(User $user, VehicleAssignment $va): bool
    {
        return $user->can('assignments.update');
    }
    public function delete(User $user, VehicleAssignment $va): bool
    {
        return $user->can('assignments.delete');
    }
}
