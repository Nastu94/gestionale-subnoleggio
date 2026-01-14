<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge:
     * - number_id: progressivo per noleggiatore (organization_id)
     *
     * Nota:
     * - In questa fase è nullable (expand).
     * - I vincoli unici e NOT NULL arriveranno dopo il backfill (fase enforce).
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {

            /**
             * Progressivo contratto per organization (noleggiatore).
             * - Nullable per compatibilità con contratti già esistenti.
             * - UnsignedInteger: i progressivi non devono essere negativi.
             */
            if (! Schema::hasColumn('rentals', 'number_id')) {
                $table->unsignedInteger('number_id')
                    ->nullable()
                    ->after('organization_id');
            }

            /**
             * Indice di supporto (NON univoco):
             * - utile per backfill e ricerche per organization + numero.
             * - Non crea problemi con i NULL.
             */
            $indexName = 'rentals_org_number_id_idx';
            if (! $this->hasIndex('rentals', $indexName)) {
                $table->index(['organization_id', 'number_id'], $indexName);
            }
        });
    }

    /**
     * Rollback:
     * - rimuove l'indice e la colonna number_id.
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {

            $indexName = 'rentals_org_number_id_idx';
            if ($this->hasIndex('rentals', $indexName)) {
                $table->dropIndex($indexName);
            }

            if (Schema::hasColumn('rentals', 'number_id')) {
                $table->dropColumn('number_id');
            }
        });
    }

    /**
     * Verifica presenza indice su tabella (compatibile MySQL).
     * Evita errori se la migration viene rilanciata in ambienti diversi.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        // Questo controllo è “best effort”: su MySQL funziona.
        // Se in futuro cambi DB, potremmo semplificare evitando il check e gestire la drop con try/catch.
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
