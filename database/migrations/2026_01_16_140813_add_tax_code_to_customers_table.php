<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge il codice fiscale all'anagrafica cliente.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            /**
             * Codice Fiscale (Italia)
             * - nullable: non sempre necessario
             * - lunghezza 16 (standard), ma teniamo 32 per robustezza (estensioni/esteri/legacy)
             * - indicizzato per ricerche veloci (opzionale, ma utile)
             */
            $table->string('tax_code', 32)
                ->nullable()
                ->after('doc_id_number');

            $table->index('tax_code');
        });
    }

    /**
     * Rimuove la colonna.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['tax_code']);
            $table->dropColumn('tax_code');
        });
    }
};
