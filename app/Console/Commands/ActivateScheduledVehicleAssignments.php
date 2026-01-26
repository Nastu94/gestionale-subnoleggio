<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleState;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Attiva le assegnazioni programmate (scheduled) quando raggiungono start_at.
 *
 * Regole:
 * - Se status=scheduled e start_at <= now:
 *   - Se end_at è null oppure end_at > now → status=active
 *   - Se end_at <= now → status=ended (è già “passata”)
 *
 * Effetti aggiuntivi (solo quando passa a ACTIVE):
 * - Aggiorna vehicles.default_pickup_location_id con la prima sede del renter (orderBy id).
 * - Allinea vehicle_states:
 *   - chiude l'eventuale stato aperto (ended_at = start_at)
 *   - garantisce l'esistenza di uno stato 'assigned' a partire da start_at
 *
 * Nota:
 * - created_by su VehicleState viene lasciato null (esecuzione via cron/CLI).
 * - Idempotente: rieseguirlo non crea duplicati di stati 'assigned' (controllo per started_at).
 */
class ActivateScheduledVehicleAssignments extends Command
{
    /**
     * Signature del comando.
     * --dry-run: mostra cosa succederebbe senza scrivere su DB.
     */
    protected $signature = 'assignments:activate-scheduled {--dry-run : Non applica modifiche, mostra solo il conteggio}';

    /** Descrizione per "php artisan list" */
    protected $description = 'Passa le assegnazioni scheduled ad active quando start_at è raggiunto e aggiorna la sede default del veicolo.';

    /**
     * Esegue il comando.
     */
    public function handle(): int
    {
        /** @var Carbon $now */
        $now = now();

        // 1) Scheduled già “finite” (end_at <= now) → ended (non ha senso attivarle)
        $endQuery = VehicleAssignment::query()
            ->where('status', 'scheduled')
            ->whereNotNull('end_at')
            ->where('end_at', '<=', $now);

        // 2) Scheduled da attivare: start_at <= now e (end_at null oppure end_at > now)
        $activateQuery = VehicleAssignment::query()
            ->where('status', 'scheduled')
            ->where('start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')
                  ->orWhere('end_at', '>', $now);
            });

        $toEndCount      = (int) $endQuery->count();
        $toActivateCount = (int) $activateQuery->count();

        if ($toEndCount === 0 && $toActivateCount === 0) {
            $this->info('Nessuna assegnazione scheduled da attivare o chiudere.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn("DRY RUN: {$toActivateCount} assegnazioni verrebbero attivate (scheduled → active).");
            if ($toEndCount > 0) {
                $this->warn("DRY RUN: {$toEndCount} assegnazioni scheduled verrebbero chiuse (scheduled → ended) perché end_at è già passato.");
            }
            return self::SUCCESS;
        }

        // Chiudo in bulk le scheduled già scadute (efficiente)
        $ended = 0;
        if ($toEndCount > 0) {
            $ended = (int) $endQuery->update([
                'status'     => 'ended',
                'updated_at' => $now,
            ]);
        }

        $activated = 0;
        $noLocation = 0;

        /**
         * Attivo le scheduled una per una:
         * serve perché dobbiamo aggiornare anche veicolo + stati in modo coerente e transazionale.
         */
        $activateQuery
            ->orderBy('start_at')
            ->chunkById(100, function ($rows) use (&$activated, &$noLocation, $now) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$activated, &$noLocation, $now) {
                        /** @var VehicleAssignment|null $va */
                        $va = VehicleAssignment::query()
                            ->whereKey($row->id)
                            ->lockForUpdate()
                            ->first();

                        if (!$va || $va->status !== 'scheduled') {
                            return; // già processata in parallelo o non più scheduled
                        }

                        // Se nel frattempo è diventata “scaduta”, la chiudo direttamente
                        if ($va->end_at && $va->end_at->lte($now)) {
                            $va->update([
                                'status'     => 'ended',
                                'updated_at' => $now,
                            ]);
                            return;
                        }

                        // Prima sede del renter (deterministica)
                        $renterLocId = Location::query()
                            ->where('organization_id', $va->renter_org_id)
                            ->orderBy('id')
                            ->value('id');

                        if ($renterLocId) {
                            Vehicle::query()
                                ->whereKey($va->vehicle_id)
                                ->update(['default_pickup_location_id' => $renterLocId]);
                        } else {
                            // Non blocco l'attivazione: segnalo e lascio il default attuale del veicolo
                            $noLocation++;
                        }

                        // scheduled → active
                        $va->update([
                            'status'     => 'active',
                            'updated_at' => $now,
                        ]);

                        /**
                         * Allineamento VehicleState:
                         * chiudo lo stato corrente al momento dell'inizio assegnazione
                         * e garantisco la presenza dello stato 'assigned' con started_at=start_at.
                         */
                        VehicleState::query()
                            ->where('vehicle_id', $va->vehicle_id)
                            ->whereNull('ended_at')
                            ->lockForUpdate()
                            ->update(['ended_at' => $va->start_at]);

                        $assignedExists = VehicleState::query()
                            ->where('vehicle_id', $va->vehicle_id)
                            ->where('state', 'assigned')
                            ->where('started_at', $va->start_at)
                            ->when(
                                $va->end_at,
                                fn ($q) => $q->where('ended_at', $va->end_at),
                                fn ($q) => $q->whereNull('ended_at')
                            )
                            ->exists();

                        if (!$assignedExists) {
                            VehicleState::create([
                                'vehicle_id' => $va->vehicle_id,
                                'state'      => 'assigned',
                                'started_at' => $va->start_at,
                                'ended_at'   => $va->end_at,
                                'reason'     => 'Attivazione scheduled → active per assegnazione #'.$va->id.' (org '.$va->renter_org_id.')',
                                'created_by' => null,
                            ]);
                        }

                        $activated++;
                    });
                }
            });

        $this->info("Assegnazioni attivate: {$activated} (scheduled → active).");
        if ($ended > 0) {
            $this->info("Assegnazioni chiuse: {$ended} (scheduled → ended, end_at passato).");
        }
        if ($noLocation > 0) {
            $this->warn("Warning: {$noLocation} attivazioni senza sedi renter (default_pickup_location_id non aggiornato).");
        }

        return self::SUCCESS;
    }
}
