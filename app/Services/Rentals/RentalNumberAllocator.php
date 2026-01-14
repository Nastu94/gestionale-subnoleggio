<?php

namespace App\Services\Rentals;

use App\Models\Organization;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RentalNumberAllocator
 *
 * Responsabilità:
 * - Allocare un numero progressivo "per noleggiatore" (organization_id) in modo concorrente-safe.
 * - Usare lock sulla riga organizations per serializzare le creazioni per la stessa organization.
 * - Scrivere audit append-only su renter_contract_number_ledger.
 *
 * Nota:
 * - Questo service NON decide come si crea un Rental: delega al chiamante tramite callback.
 * - In questo modo potrai integrarlo nel wizard senza stravolgere il codice esistente.
 */
class RentalNumberAllocator
{
    /**
     * Esegue allocazione + creazione contratto in modo atomico.
     *
     * @param  int      $organizationId  Noleggiatore (renter) = rentals.organization_id
     * @param  int|null $createdBy       Utente che crea (per audit). Nel backfill può essere null.
     * @param  callable $createRental    Callback che riceve $numberId e DEVE creare e salvare il Rental.
     *                                  Firma: fn(int $numberId): Rental
     *
     * @return Rental
     */
    public function allocateAndCreate(int $organizationId, ?int $createdBy, callable $createRental): Rental
    {
        /**
         * Retry su deadlock/lock contention (MySQL) in caso di concorrenza.
         * 3 tentativi sono un compromesso ragionevole.
         */
        return DB::transaction(function () use ($organizationId, $createdBy, $createRental) {

            /**
             * Lock "per noleggiatore": serializza le allocazioni per organization_id.
             * Importante: questo lock deve essere usato anche dal backfill per coerenza.
             */
            Organization::query()
                ->whereKey($organizationId)
                ->lockForUpdate()
                ->firstOrFail();

            /**
             * Calcolo ultimo numero assegnato (fonte: ledger).
             * Con il lock sopra, due transazioni parallele sullo stesso organization_id
             * non possono leggere e assegnare lo stesso next.
             */
            $last = DB::table('renter_contract_number_ledger')
                ->where('organization_id', $organizationId)
                ->max('number_id');

            $nextNumberId = ((int) $last) + 1;
            if ($nextNumberId < 1) {
                // Difesa extra: in teoria non dovrebbe mai accadere.
                $nextNumberId = 1;
            }

            /**
             * Creazione Rental demandata al wizard (callback).
             * Il wizard dovrà valorizzare rentals.number_id con $nextNumberId.
             */
            $rental = $createRental($nextNumberId);

            if (! $rental instanceof Rental) {
                throw new RuntimeException('La callback createRental() deve restituire un modello Rental.');
            }

            if (! $rental->exists || ! $rental->getKey()) {
                throw new RuntimeException('Il Rental deve essere salvato nel database prima di scrivere il ledger.');
            }

            /**
             * Audit append-only.
             * In questa fase (B1) non abbiamo vincoli unici, ma li aggiungeremo in enforce.
             */
            DB::table('renter_contract_number_ledger')->insert([
                'organization_id' => $organizationId,
                'rental_id'        => (int) $rental->getKey(),
                'number_id'        => $nextNumberId,
                'created_by'       => $createdBy,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return $rental;

        }, 3);
    }
}
