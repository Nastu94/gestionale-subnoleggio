<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge il riferimento LUOGO CARGOS alle locations.
 *
 * NOTE:
 * - Il campo è nullable: una location può esistere
 *   anche senza essere ancora mappata a CARGOS
 * - Nessuna FK rigida: master data esterna
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            /**
             * Codice LUOGO CARGOS.
             * Riferisce a cargos_luoghi.code
             */
            $table->unsignedInteger('police_place_code')
                ->nullable()
                ->after('postal_code');

            /**
             * Indice per lookup rapidi in fase di invio CARGOS.
             */
            $table->index('police_place_code');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['police_place_code']);
            $table->dropColumn('police_place_code');
        });
    }
};
