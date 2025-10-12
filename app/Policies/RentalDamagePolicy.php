<?php

namespace App\Policies;

use App\Models\User;
use App\Models\RentalDamage;

/**
 * Policy: RentalDamage
 * - Controlla CRUD dei danni e gestione media collegati.
 */
class RentalDamagePolicy
{
    /**
     * Creazione danno.
     * Permesso: rental_damages.create
     */
    public function create(User $user): bool
    {
        return $user->can('rental_damages.create');
    }

    /**
     * Aggiornamento danno.
     * Permesso: rental_damages.update
     */
    public function update(User $user, RentalDamage $damage): bool
    {
        return $user->can('rental_damages.update');
    }

    /**
     * Eliminazione danno.
     * Permesso: rental_damages.delete
     */
    public function delete(User $user, RentalDamage $damage): bool
    {
        return $user->can('rental_damages.delete');
    }

    /**
     * Upload foto del danno.
     * Permesso: media.attach.damage_photo
     */
    public function uploadPhoto(User $user, RentalDamage $damage): bool
    {
        return $user->can('media.attach.damage_photo');
    }

    /**
     * Eliminazione media collegati al danno.
     * Permesso: media.delete
     */
    public function deleteMedia(User $user, RentalDamage $damage): bool
    {
        return $user->can('media.delete');
    }
}
