<?php

namespace App\Policies;

use App\Models\User;
use App\Models\RentalChecklist;

/**
 * Policy: RentalChecklist
 * - Controlla creazione/aggiornamento e gestione media collegati alla checklist.
 * - Usa i permessi granulari definiti nel seeder.
 */
class RentalChecklistPolicy
{
    /**
     * Creazione checklist (pickup/return).
     * Permesso richiesto: rental_checklists.create
     */
    public function create(User $user): bool
    {
        return $user->can('rental_checklists.create');
    }

    /**
     * Aggiornamento checklist.
     * Permesso richiesto: rental_checklists.update
     */
    public function update(User $user, RentalChecklist $checklist): bool
    {
        return $user->can('rental_checklists.update');
    }

    /**
     * Upload foto sulla checklist.
     * Permesso richiesto: media.attach.checklist_photo
     */
    public function uploadPhoto(User $user, RentalChecklist $checklist): bool
    {
        return $user->can('media.attach.checklist_photo');
    }

    /**
     * Upload firma (immagine/PDF) sulla checklist.
     * Permesso richiesto: media.upload (generico)
     */
    public function uploadSignature(User $user, RentalChecklist $checklist): bool
    {
        return $user->can('media.upload');
    }

    /**
     * Eliminazione media collegati alla checklist.
     * Permesso richiesto: media.delete
     */
    public function deleteMedia(User $user, RentalChecklist $checklist): bool
    {
        return $user->can('media.delete');
    }
}
