<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfill massivo di vehicles.default_pickup_location_id.
 *
 * Regole:
 * - Se esiste un'assegnazione "corrente" (active oppure scheduled già iniziata):
 *   -> default_pickup_location_id = prima sede del renter (min id locations per organization_id=renter_org_id)
 * - Altrimenti:
 *   -> default_pickup_location_id = prima sede dell'admin org del veicolo (min id locations per organization_id=admin_organization_id)
 *
 * Note:
 * - Idempotente: puoi rilanciarlo senza creare effetti collaterali (aggiorna solo se cambia valore).
 * - Non modifica status assegnazioni: si limita al campo default_pickup_location_id.
 * - Supporta --dry-run per vedere cosa cambierebbe.
 */
class BackfillVehicleDefaultPickupLocation extends Command
{
    /**
     * Signature del comando.
     * --dry-run: non scrive su DB, mostra solo conteggi.
     * --chunk: dimensione chunk per elaborazione (default 200).
     */
    protected $signature = 'vehicles:backfill-default-pickup
        {--dry-run : Non applica modifiche, mostra solo il conteggio}
        {--chunk=200 : Numero di veicoli processati per chunk}';

    /** Descrizione comando */
    protected $description = 'Ricalcola default_pickup_location_id dei veicoli in base ad assegnazioni correnti o sede admin.';

    /**
     * Esegue il comando.
     */
    public function handle(): int
    {
        /** @var Carbon $now */
        $now = now();

        $dryRun = (bool) $this->option('dry-run');
        $chunk  = max(50, (int) $this->option('chunk')); // minimo ragionevole

        $this->info('Backfill default_pickup_location_id avviato' . ($dryRun ? ' (DRY RUN).' : '.'));

        $updated = 0;
        $skippedNoAdminLoc = 0;
        $skippedNoRenterLoc = 0;
        $skippedNoAdminOrg = 0;

        Vehicle::query()
            ->select(['id', 'admin_organization_id', 'default_pickup_location_id'])
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $vehicles) use (
                $now,
                $dryRun,
                &$updated,
                &$skippedNoAdminLoc,
                &$skippedNoRenterLoc,
                &$skippedNoAdminOrg
            ) {
                $vehicleIds = $vehicles->pluck('id')->all();

                /**
                 * 1) Recupera assegnazioni "correnti" per i veicoli del chunk.
                 * Consideriamo correnti:
                 * - status in (active, scheduled)
                 * - start_at <= now
                 * - end_at null OR end_at > now
                 *
                 * Se per un veicolo ci fossero più righe (anomalia), prendiamo la più recente per start_at.
                 */
                $currentAssignments = VehicleAssignment::query()
                    ->select(['vehicle_id', 'renter_org_id', 'start_at'])
                    ->whereIn('vehicle_id', $vehicleIds)
                    ->whereIn('status', ['active', 'scheduled'])
                    ->where('start_at', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_at')
                          ->orWhere('end_at', '>', $now);
                    })
                    ->orderBy('start_at', 'desc')
                    ->get()
                    ->groupBy('vehicle_id')
                    ->map(function (Collection $rows) {
                        return $rows->first(); // grazie all'orderBy desc, è la più recente
                    });

                $renterOrgIds = $currentAssignments
                    ->pluck('renter_org_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                /**
                 * 2) Mappa: org_id -> first_location_id (MIN(id)).
                 * Facciamo due mappe: renter e admin.
                 */
                $renterFirstLocByOrg = $this->firstLocationIdMapByOrgIds($renterOrgIds);

                $adminOrgIds = $vehicles
                    ->pluck('admin_organization_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $adminFirstLocByOrg = $this->firstLocationIdMapByOrgIds($adminOrgIds);

                /**
                 * 3) Applica regole e aggiorna (solo se cambia).
                 */
                DB::transaction(function () use (
                    $vehicles,
                    $currentAssignments,
                    $renterFirstLocByOrg,
                    $adminFirstLocByOrg,
                    $dryRun,
                    &$updated,
                    &$skippedNoAdminLoc,
                    &$skippedNoRenterLoc,
                    &$skippedNoAdminOrg
                ) {
                    foreach ($vehicles as $vehicle) {
                        // Se manca admin org, non possiamo applicare fallback admin
                        if (!$vehicle->admin_organization_id) {
                            $skippedNoAdminOrg++;
                            continue;
                        }

                        $desiredLocId = null;

                        // Regola 1: se c'è assegnazione corrente → prima sede renter
                        $assignment = $currentAssignments->get($vehicle->id);
                        if ($assignment) {
                            $renterLocId = $renterFirstLocByOrg[$assignment->renter_org_id] ?? null;

                            if ($renterLocId) {
                                $desiredLocId = (int) $renterLocId;
                            } else {
                                // Renter senza sedi: non possiamo impostare pickup su renter
                                $skippedNoRenterLoc++;
                            }
                        }

                        // Regola 2: fallback admin → prima sede admin
                        if (!$desiredLocId) {
                            $adminLocId = $adminFirstLocByOrg[$vehicle->admin_organization_id] ?? null;

                            if ($adminLocId) {
                                $desiredLocId = (int) $adminLocId;
                            } else {
                                $skippedNoAdminLoc++;
                                continue;
                            }
                        }

                        // Nessuna modifica necessaria
                        if ((int) ($vehicle->default_pickup_location_id ?? 0) === (int) $desiredLocId) {
                            continue;
                        }

                        // Dry-run: non scrive
                        if ($dryRun) {
                            $updated++;
                            continue;
                        }

                        // Update mirato senza eventi (query builder)
                        Vehicle::query()
                            ->whereKey($vehicle->id)
                            ->update(['default_pickup_location_id' => $desiredLocId]);

                        $updated++;
                    }
                });
            });

        $this->info("Aggiornamenti (o aggiornabili in dry-run): {$updated}");
        $this->line("Saltati (admin_organization_id mancante): {$skippedNoAdminOrg}");
        $this->line("Saltati (admin senza sedi): {$skippedNoAdminLoc}");
        $this->line("Avvisi (renter senza sedi durante assegnazione corrente): {$skippedNoRenterLoc}");

        return self::SUCCESS;
    }

    /**
     * Costruisce una mappa org_id => first_location_id (MIN(id)).
     *
     * @param array<int> $orgIds
     * @return array<int,int> (organization_id => location_id)
     */
    protected function firstLocationIdMapByOrgIds(array $orgIds): array
    {
        if (empty($orgIds)) {
            return [];
        }

        return Location::query()
            ->whereIn('organization_id', $orgIds)
            ->selectRaw('organization_id, MIN(id) as first_location_id')
            ->groupBy('organization_id')
            ->pluck('first_location_id', 'organization_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }
}
