<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Rental;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command: BackfillRentalNumberIds
 *
 * Scopo:
 * - Assegna rentals.number_id (progressivo per rentals.organization_id) ai record storici dove è NULL.
 * - Scrive audit append-only nella tabella renter_contract_number_ledger (solo INSERT).
 *
 * Caratteristiche:
 * - Idempotente: lavora solo su rentals con number_id NULL e inserisce ledger solo se mancante.
 * - Concorrenza-safe: usa lockForUpdate sulla riga organizations (stesso lock che useremo nel wizard).
 */
class BackfillRentalNumberIds extends Command
{
    /**
     * Firma comando.
     * - --only-org: limita il backfill a una specifica organization_id (utile per test)
     * - --created-by: user_id da salvare in audit (opzionale; se omesso resta null)
     * - --dry-run: non scrive nulla, mostra solo cosa farebbe
     *
     * @var string
     */
    protected $signature = 'rentals:backfill-number-ids
                            {--only-org= : Esegui solo per questo organization_id}
                            {--created-by= : User ID per audit (opzionale)}
                            {--dry-run : Non scrive, solo anteprima}';

    /**
     * Descrizione.
     *
     * @var string
     */
    protected $description = 'Backfill rentals.number_id per organization_id e inserisce audit in renter_contract_number_ledger (lock-safe).';

    /**
     * Esecuzione comando.
     */
    public function handle(): int
    {
        $onlyOrgId = $this->option('only-org') ? (int) $this->option('only-org') : null;
        $createdBy = $this->option('created-by') !== null ? (int) $this->option('created-by') : null;
        $dryRun    = (bool) $this->option('dry-run');

        $orgIdsQuery = Rental::query()
            ->select('organization_id')
            ->whereNotNull('organization_id') // già garantito da te, ma teniamo guardrail
            ->whereNull('number_id')
            ->distinct()
            ->orderBy('organization_id');

        if ($onlyOrgId) {
            $orgIdsQuery->where('organization_id', $onlyOrgId);
        }

        $orgIds = $orgIdsQuery->pluck('organization_id')->all();

        if (empty($orgIds)) {
            $this->info('Nessun rental da backfillare: tutte le righe hanno già number_id.');
            return self::SUCCESS;
        }

        $this->info('Organization da processare: '.count($orgIds));
        if ($dryRun) {
            $this->warn('DRY RUN attivo: non verrà scritto nulla.');
        }

        $totalUpdated = 0;
        $totalLedgerInserted = 0;

        foreach ($orgIds as $orgId) {
            DB::transaction(function () use ($orgId, $createdBy, $dryRun, &$totalUpdated, &$totalLedgerInserted) {

                /**
                 * Lock per organization:
                 * serializza numerazioni concorrenti per lo stesso noleggiatore.
                 */
                Organization::query()
                    ->whereKey($orgId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /**
                 * Calcola il punto di partenza:
                 * - massimo già allocato nel ledger (fonte principale)
                 * - in fallback, massimo già presente in rentals (difesa extra)
                 */
                $maxLedger = (int) DB::table('renter_contract_number_ledger')
                    ->where('organization_id', $orgId)
                    ->max('number_id');

                $maxRentals = (int) Rental::query()
                    ->where('organization_id', $orgId)
                    ->whereNotNull('number_id')
                    ->max('number_id');

                $nextNumber = max($maxLedger, $maxRentals) + 1;
                if ($nextNumber < 1) {
                    $nextNumber = 1;
                }

                /**
                 * Recupera i rental da backfillare per questa organization.
                 * Ordinamento deterministico: created_at ASC, id ASC.
                 */
                $rentals = Rental::query()
                    ->where('organization_id', $orgId)
                    ->whereNull('number_id')
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                if ($rentals->isEmpty()) {
                    return;
                }

                $this->line("Org #{$orgId}: rentals da backfillare = ".$rentals->count()." (start={$nextNumber})");

                foreach ($rentals as $rental) {
                    $assigned = $nextNumber;
                    $nextNumber++;

                    /**
                     * Update rentals.number_id (backfill).
                     * Nota: qui stiamo aggiornando rentals (non il ledger), come previsto dal design.
                     */
                    if (! $dryRun) {
                        $rental->number_id = $assigned;
                        $rental->save();
                    }

                    $totalUpdated++;

                    /**
                     * Inserimento audit append-only:
                     * Inseriamo solo se non esiste già una riga per (organization_id, rental_id).
                     * In fase B1 non ci sono unique: questo check evita duplicati.
                     */
                    $exists = DB::table('renter_contract_number_ledger')
                        ->where('organization_id', $orgId)
                        ->where('rental_id', (int) $rental->id)
                        ->exists();

                    if (! $exists) {
                        if (! $dryRun) {
                            DB::table('renter_contract_number_ledger')->insert([
                                'organization_id' => $orgId,
                                'rental_id'        => (int) $rental->id,
                                'number_id'        => $assigned,
                                'created_by'       => $createdBy,
                                'created_at'       => $rental->created_at ?? now(),
                                'updated_at'       => $rental->created_at ?? now(),
                            ]);
                        }
                        $totalLedgerInserted++;
                    }
                }
            }, 3); // retry transazione in caso di deadlock/lock contention
        }

        $this->info("Backfill completato. Rentals aggiornati: {$totalUpdated}. Ledger inseriti: {$totalLedgerInserted}.");

        return self::SUCCESS;
    }
}
