<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce:
     * - rentals.number_id NOT NULL
     * - UNIQUE (organization_id, number_id) su rentals
     * - UNIQUE (organization_id, number_id) e UNIQUE (organization_id, rental_id) su ledger
     *
     * Nota:
     * - Questa migration fallisce volutamente se ci sono number_id NULL o duplicati.
     *   Così evitiamo “inconsistenze silenziose” in produzione.
     */
    public function up(): void
    {
        /**
         * 1) Guardrail: non devono esistere rentals senza number_id.
         */
        $hasNulls = DB::table('rentals')->whereNull('number_id')->exists();
        if ($hasNulls) {
            throw new RuntimeException(
                'Impossibile applicare vincoli: esistono rentals con number_id NULL. Esegui prima il backfill.'
            );
        }

        /**
         * 2) Guardrail: no duplicati su rentals (organization_id, number_id).
         */
        $dupRentals = DB::table('rentals')
            ->select('organization_id', 'number_id', DB::raw('COUNT(*) as c'))
            ->groupBy('organization_id', 'number_id')
            ->having('c', '>', 1)
            ->limit(1)
            ->exists();

        if ($dupRentals) {
            throw new RuntimeException(
                'Impossibile applicare UNIQUE su rentals: trovati duplicati (organization_id, number_id).'
            );
        }

        /**
         * 3) Guardrail: no duplicati su ledger.
         * - (organization_id, number_id)
         * - (organization_id, rental_id)
         */
        $dupLedgerOrgNumber = DB::table('renter_contract_number_ledger')
            ->select('organization_id', 'number_id', DB::raw('COUNT(*) as c'))
            ->groupBy('organization_id', 'number_id')
            ->having('c', '>', 1)
            ->limit(1)
            ->exists();

        if ($dupLedgerOrgNumber) {
            throw new RuntimeException(
                'Impossibile applicare UNIQUE su ledger: trovati duplicati (organization_id, number_id).'
            );
        }

        $dupLedgerOrgRental = DB::table('renter_contract_number_ledger')
            ->select('organization_id', 'rental_id', DB::raw('COUNT(*) as c'))
            ->groupBy('organization_id', 'rental_id')
            ->having('c', '>', 1)
            ->limit(1)
            ->exists();

        if ($dupLedgerOrgRental) {
            throw new RuntimeException(
                'Impossibile applicare UNIQUE su ledger: trovati duplicati (organization_id, rental_id).'
            );
        }

        /**
         * 4) Rendi NOT NULL rentals.number_id.
         * Usiamo SQL raw per MySQL/MariaDB (Laragon/Plesk tipicamente).
         */
        DB::statement('ALTER TABLE rentals MODIFY number_id INT UNSIGNED NOT NULL');

        /**
         * 5) Sostituisci indici NON unici con vincoli unici (enforce).
         */
        Schema::table('rentals', function (Blueprint $table) {

            // Drop indice non unico creato in fase A (se presente)
            $oldIndex = 'rentals_org_number_id_idx';
            if ($this->hasIndex('rentals', $oldIndex)) {
                $table->dropIndex($oldIndex);
            }

            // UNIQUE per progressivo per noleggiatore
            $table->unique(['organization_id', 'number_id'], 'rentals_org_number_unique');
        });

        Schema::table('renter_contract_number_ledger', function (Blueprint $table) {

            // Drop indici non unici creati in fase B (se presenti)
            $idx1 = 'renter_contract_number_ledger_org_number_idx';
            if ($this->hasIndex('renter_contract_number_ledger', $idx1)) {
                $table->dropIndex($idx1);
            }

            $idx2 = 'renter_contract_number_ledger_org_rental_idx';
            if ($this->hasIndex('renter_contract_number_ledger', $idx2)) {
                $table->dropIndex($idx2);
            }

            // UNIQUE enforce
            $table->unique(['organization_id', 'number_id'], 'ledger_org_number_unique');
            $table->unique(['organization_id', 'rental_id'], 'ledger_org_rental_unique');
        });
    }

    /**
     * Rollback:
     * - rimuove i vincoli unici
     * - ripristina indice non unico (facoltativo)
     * - rende rentals.number_id nuovamente nullable
     */
    public function down(): void
    {
        Schema::table('renter_contract_number_ledger', function (Blueprint $table) {
            if ($this->hasIndex('renter_contract_number_ledger', 'ledger_org_number_unique')) {
                $table->dropUnique('ledger_org_number_unique');
            }
            if ($this->hasIndex('renter_contract_number_ledger', 'ledger_org_rental_unique')) {
                $table->dropUnique('ledger_org_rental_unique');
            }

            // Ripristino indici non unici (come in fase B)
            $table->index(['organization_id', 'number_id'], 'renter_contract_number_ledger_org_number_idx');
            $table->index(['organization_id', 'rental_id'], 'renter_contract_number_ledger_org_rental_idx');
        });

        Schema::table('rentals', function (Blueprint $table) {
            if ($this->hasIndex('rentals', 'rentals_org_number_unique')) {
                $table->dropUnique('rentals_org_number_unique');
            }

            // Ripristino indice non unico (come in fase A)
            $table->index(['organization_id', 'number_id'], 'rentals_org_number_id_idx');
        });

        // Torna nullable (MySQL/MariaDB)
        DB::statement('ALTER TABLE rentals MODIFY number_id INT UNSIGNED NULL');
    }

    /**
     * Verifica presenza indice/unique per nome (best effort su MySQL).
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $indexes = $schemaManager->listTableIndexes($table);

            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
