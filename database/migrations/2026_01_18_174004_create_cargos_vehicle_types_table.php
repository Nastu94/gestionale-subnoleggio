<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella: cargos_vehicle_types
 *
 * Master data ufficiale CARGOS.
 * Contiene le tipologie di veicolo secondo la classificazione
 * della Polizia di Stato (autovettura, motociclo, autocarro, ecc.).
 *
 * NOTE:
 * - Il campo "code" è il valore ufficiale CARGOS
 * - NON sostituisce carburante, segmento o trasmissione
 * - La tabella è piccola e stabile
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos_vehicle_types', function (Blueprint $table) {
            /**
             * Codice tipo veicolo CARGOS.
             * È il valore da inviare nel payload (VEICOLO_TIPO).
             */
            $table->string('code')->primary();

            /**
             * Etichetta descrittiva del tipo veicolo.
             * Serve solo per UI e mapping.
             */
            $table->string('label');

            /**
             * Flag di attivazione.
             * Permette di gestire eventuali dismissioni future
             * senza perdere coerenza storica.
             */
            $table->boolean('is_active')->default(true);

            /**
             * Timestamp standard Laravel.
             */
            $table->timestamps();

            /**
             * Indice per ricerche rapide in UI.
             */
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_vehicle_types');
    }
};
