<?php

namespace App\Console\Commands;

use App\Models\VehicleAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Chiude le assegnazioni scadute.
 *
 * Regola:
 * - Se end_at è valorizzato e <= adesso, un'assegnazione non può restare 'active'.
 * - Aggiorniamo lo status a 'ended' per mantenere coerenza DB/UI.
 *
 * Nota:
 * - Non tocchiamo VehicleState in questo step: qui l'obiettivo è correggere lo status in vehicle_assignments.
 * - Idempotente: rieseguirlo non crea effetti collaterali (aggiorna solo righe ancora attive).
 */
class CloseExpiredVehicleAssignments extends Command
{
    /**
     * Signature del comando.
     * --dry-run: mostra quante righe verrebbero aggiornate senza modificare il DB.
     */
    protected $signature = 'assignments:close-expired {--dry-run : Non applica modifiche, mostra solo il conteggio}';

    /** Descrizione per "php artisan list" */
    protected $description = 'Imposta status=ended per le assegnazioni con end_at passato ma ancora active.';

    /**
     * Esegue il comando.
     */
    public function handle(): int
    {
        /** @var Carbon $now */
        $now = now();

        // Query: assegnazioni "attive" ma con end_at già passato (quindi da chiudere).
        $query = VehicleAssignment::query()
            ->where('status', 'active')
            ->whereNotNull('end_at')
            ->where('end_at', '<=', $now);

        $count = (int) $query->count();

        if ($count === 0) {
            $this->info('Nessuna assegnazione scaduta da chiudere.');
            return self::SUCCESS;
        }

        // Modalità simulazione (non scrive su DB).
        if ((bool) $this->option('dry-run')) {
            $this->warn("DRY RUN: {$count} assegnazioni verrebbero impostate a status=ended.");
            return self::SUCCESS;
        }

        /**
         * Update bulk:
         * - Più efficiente della chiusura record-by-record.
         * - updated_at viene mantenuto coerente.
         */
        $updated = (int) $query->update([
            'status'     => 'ended',
            'updated_at' => $now,
        ]);

        $this->info("Assegnazioni chiuse: {$updated} (status=ended).");

        return self::SUCCESS;
    }
}
