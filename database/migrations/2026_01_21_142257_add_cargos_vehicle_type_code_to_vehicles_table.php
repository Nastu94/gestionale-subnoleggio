<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge il campo "cargos_vehicle_type_code" alla tabella "vehicles".
 *
 * Salviamo il "code" ufficiale CARGOS (cargos_vehicle_types.code) direttamente in vehicles
 * perché il payload CARGOS deve leggere il dato dalla tabella vehicles (no FK).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            /**
             * Codice tipologia veicolo CARGOS (es: valore per VEICOLO_TIPO).
             * NOTA: lo lasciamo nullable per non rompere i record già esistenti;
             * lato applicazione lo renderemo obbligatorio in create/edit.
             */
            $table->string('cargos_vehicle_type_code', 32)
                ->nullable()
                ->after('segment')
                ->comment('Codice tipologia veicolo CARGOS (cargos_vehicle_types.code)');

            /**
             * Indice per lookup/filtri rapidi.
             */
            $table->index(
                'cargos_vehicle_type_code',
                'vehicles_cargos_vehicle_type_code_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_cargos_vehicle_type_code_idx');
            $table->dropColumn('cargos_vehicle_type_code');
        });
    }
};
