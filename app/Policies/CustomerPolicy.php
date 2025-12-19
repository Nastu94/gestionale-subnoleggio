<?php

namespace App\Policies;

use App\Models\{User, Customer};
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('customers.viewAny');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if ($user->organization->isRenter()) {
            return $customer->organization_id === $user->organization_id && $user->can('customers.update');
        }
        return $user->can('customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        if ($user->organization->isRenter()) {
            return $customer->organization_id === $user->organization_id && $user->can('customers.delete');
        }
        return $user->can('customers.delete');
    }
}
