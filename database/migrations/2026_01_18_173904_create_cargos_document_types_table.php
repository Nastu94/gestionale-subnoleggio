<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella: cargos_document_types
 *
 * Master data ufficiale CARGOS.
 * Contiene le tipologie di documento di identità ammesse
 * dalla Polizia di Stato (es. Carta d'identità, Passaporto, ecc.).
 *
 * NOTE:
 * - Il campo "code" è il valore ufficiale CARGOS
 * - La tabella è piccola e stabile
 * - I dati vengono inseriti tramite seeder
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos_document_types', function (Blueprint $table) {
            /**
             * Codice documento CARGOS.
             * È il valore da inviare nel payload (DOCUMENTO_TIPO_COD).
             */
            $table->string('code')->primary();

            /**
             * Etichetta descrittiva del documento.
             * Usata esclusivamente per la UI.
             */
            $table->string('label');

            /**
             * Flag di attivazione.
             * Serve per gestire eventuali dismissioni future
             * senza perdere lo storico.
             */
            $table->boolean('is_active')->default(true);

            /**
             * Timestamp standard Laravel.
             */
            $table->timestamps();

            /**
             * Indice per lookup rapidi in UI.
             */
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_document_types');
    }
};
