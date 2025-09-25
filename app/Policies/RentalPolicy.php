<?php

namespace App\Policies;

use App\Models\{User, Rental};
use Illuminate\Auth\Access\HandlesAuthorization;

class RentalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('rentals.viewAny');
    }

    public function view(User $user, Rental $rental): bool
    {
        if ($user->organization->isRenter()) {
            return $rental->organization_id === $user->organization_id && $user->can('rentals.view');
        }
        return $user->can('rentals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('rentals.create');
    }

    public function update(User $user, Rental $rental): bool
    {
        if ($user->organization->isRenter()) {
            return $rental->organization_id === $user->organization_id && $user->can('rentals.update');
        }
        return $user->can('rentals.update');
    }

    public function delete(User $user, Rental $rental): bool
    {
        if ($user->organization->isRenter()) {
            return $rental->organization_id === $user->organization_id && $user->can('rentals.delete');
        }
        return $user->can('rentals.delete');
    }
}
