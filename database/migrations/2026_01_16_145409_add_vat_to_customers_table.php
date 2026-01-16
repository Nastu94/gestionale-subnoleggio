<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge la Partita IVA all'anagrafica clienti.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            /**
             * Partita IVA (Italia)
             * - nullable: non sempre presente (privati)
             * - string 32 per compatibilità con eventuali formati legacy/esteri
             * - index: utile per ricerche e dedupe (opzionale ma consigliato)
             */
            $table->string('vat', 32)
                ->nullable()
                ->after('tax_code');

            $table->index('vat');
        });
    }

    /**
     * Ripristina lo schema rimuovendo il campo vat.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['vat']);
            $table->dropColumn('vat');
        });
    }
};
