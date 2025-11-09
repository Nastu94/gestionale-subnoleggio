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

    public function checkout(User $user, Rental $rental): bool
    {
        return $user->can('rentals.checkout');
    }
    public function inuse(User $user, Rental $rental): bool
    {
        return $user->can('rentals.inuse');
    }
    public function checkin(User $user, Rental $rental): bool
    {
        return $user->can('rentals.checkin');
    }
    public function close(User $user, Rental $rental): bool
    {
        // Permesso base a chiudere
        return $user->can('rentals.close') || $user->can('rentals.close.override');
    }
    public function cancel(User $user, Rental $rental): bool
    {
        return $user->can('rentals.cancel');
    }
    public function noshow(User $user, Rental $rental): bool
    {
        return $user->can('rentals.noshow');
    }

    // Contratti
    public function contractGenerate(User $user, Rental $rental): bool
    {
        // Permesso specifico + visibilità sull'oggetto
        return $user->can('rentals.contract.generate') && $this->view($user, $rental);
    }
    public function contractUploadSigned(User $user, Rental $rental): bool
    {
        return $user->can('rentals.contract.upload_signed') && $this->view($user, $rental);
    }

    // Media sul Rental
    public function uploadMedia(User $user, Rental $rental): bool
    {
        return $user->can('media.upload');
    }
    public function deleteMedia(User $user, Rental $rental): bool
    {
        return $user->can('media.delete');
    }
}
