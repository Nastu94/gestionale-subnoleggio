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
     * Creazione/modifica di un danno.
     * Permesso richiesto (esempio): rental_damages.update
     * Blocco: vietato se la checklist padre è locked.
     */
    public function update(User $user, RentalDamage $damage): bool
    {
        $checklist = $damage->rental?->checklists()
            ->where('type', $damage->phase) // pickup/return/during
            ->first();

        if ($checklist && $checklist->isLocked()) {
            return false; // vieta modifiche se la checklist di quella fase è bloccata
        }

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
     * Upload foto sul danno.
     * Permesso richiesto: media.attach.damage_photo
     * Blocco: vietato se la checklist padre è locked.
     */
    public function uploadPhoto(User $user, RentalDamage $damage): bool
    {
        $checklist = $damage->rental?->checklists()
            ->where('type', $damage->phase) // pickup/return/during
            ->first();

        if ($checklist && $checklist->isLocked()) {
            return false; // vieta modifiche se la checklist di quella fase è bloccata
        }

        return $user->can('media.attach.damage_photo');
    }

    /**
     * Eliminazione media associati al danno.
     * Permesso richiesto: media.delete
     * Blocco: vietato se la checklist padre è locked.
     */
    public function deleteMedia(User $user, RentalDamage $damage): bool
    {
        $checklist = $damage->rental?->checklists()
            ->where('type', $damage->phase) // pickup/return/during
            ->first();

        if ($checklist && $checklist->isLocked()) {
            return false; // vieta modifiche se la checklist di quella fase è bloccata
        }

        return $user->can('media.delete');
    }
}
