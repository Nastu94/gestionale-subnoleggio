<?php

namespace App\Policies;

use App\Models\User;
use App\Models\RentalChecklist;

/**
 * Policy: RentalChecklist
 * - Controlla creazione/aggiornamento e gestione media collegati alla checklist.
 * - Usa i permessi granulari definiti nel seeder.
 * - Applica il blocco persistente (Opzione B): se locked_at non è null, l'oggetto è in sola lettura.
 */
class RentalChecklistPolicy
{
    /**
     * Creazione checklist (pickup/return).
     * Permesso richiesto: rental_checklists.create
     */
    public function create(User $user): bool
    {
        // La creazione non ha oggetto "locked", si basa solo sul permesso.
        return $user->can('rental_checklists.create');
    }

    /**
     * Aggiornamento checklist.
     * Permesso richiesto: rental_checklists.update
     * Blocco: vietato se la checklist è locked.
     */
    public function update(User $user, RentalChecklist $checklist): bool
    {
        // Stop: oggetto in sola lettura se locked
        if ($checklist->isLocked()) {
            return false;
        }

        return $user->can('rental_checklists.update');
    }

    /**
     * Upload foto sulla checklist (gestito via Livewire secondo la tua nuova scelta).
     * Permesso richiesto: media.attach.checklist_photo
     * Blocco: vietato se locked.
     */
    public function uploadPhoto(User $user, RentalChecklist $checklist): bool
    {
        if ($checklist->isLocked()) {
            return false;
        }

        return $user->can('media.attach.checklist_photo');
    }

    /**
     * Upload firma (immagine/PDF) sulla checklist (es. bozza firma locale).
     * Permesso richiesto: media.upload (generico)
     * Blocco: vietato se locked.
     *
     * Nota: il PDF firmato "definitivo" verrà caricato nella collection dedicata
     * (checklist_{pickup|return}_signed) e causerà il lock; fino a quel momento
     * resta consentito caricare allegati NON firmati se l'oggetto è unlocked.
     */
    public function uploadSignature(User $user, RentalChecklist $checklist): bool
    {
        if ($checklist->isLocked()) {
            return false;
        }

        return $user->can('media.upload');
    }

    /**
     * Eliminazione media collegati alla checklist.
     * Permesso richiesto: media.delete
     * Blocco: vietato se locked (non si deve alterare la prova documentale).
     */
    public function deleteMedia(User $user, RentalChecklist $checklist): bool
    {
        if ($checklist->isLocked()) {
            return false;
        }

        return $user->can('media.delete');
    }

    /**
     * (Opzionale) Autorizzazione alla generazione del PDF checklist.
     * Usiamo la stessa logica dell'update: consentito solo se unlocked.
     * Non introduce nuovi permessi stringa.
     */
    public function generatePdf(User $user, RentalChecklist $checklist): bool
    {
        if ($checklist->isLocked()) {
            return false;
        }

        // Riutilizziamo il permesso di update per la generazione PDF in bozza.
        return $user->can('rental_checklists.update');
    }
}
