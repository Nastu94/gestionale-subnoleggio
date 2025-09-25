<?php

namespace App\Policies;

use App\Models\{User, Location};
use Illuminate\Auth\Access\HandlesAuthorization;

class LocationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('locations.viewAny');
    }

    public function view(User $user, Location $location): bool
    {
        if ($user->organization->isRenter()) {
            return $location->organization_id === $user->organization_id && $user->can('locations.view');
        }
        return $user->can('locations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('locations.create');
    }

    public function update(User $user, Location $location): bool
    {
        if ($user->organization->isRenter()) {
            return $location->organization_id === $user->organization_id && $user->can('locations.update');
        }
        return $user->can('locations.update');
    }

    public function delete(User $user, Location $location): bool
    {
        if ($user->organization->isRenter()) {
            return $location->organization_id === $user->organization_id && $user->can('locations.delete');
        }
        return $user->can('locations.delete');
    }
}
