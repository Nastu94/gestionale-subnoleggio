<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella: cargos_payment_types
 *
 * Master data ufficiale CARGOS.
 * Contiene le tipologie di pagamento secondo la classificazione
 * della Polizia di Stato.
 *
 * IMPORTANTE:
 * - NON rappresenta il metodo tecnico di pagamento (POS, Stripe, ecc.)
 * - Rappresenta la modalità contrattuale dichiarata
 * - Spesso è sufficiente usare pochi codici fissi
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos_payment_types', function (Blueprint $table) {
            /**
             * Codice tipo pagamento CARGOS.
             * È il valore da inviare nel payload (PAGAMENTO_TIPO).
             */
            $table->string('code')->primary();

            /**
             * Etichetta descrittiva.
             * Usata solo per UI e configurazione.
             */
            $table->string('label');

            /**
             * Flag di attivazione.
             * Permette di dismettere codici non più usati
             * senza rompere lo storico.
             */
            $table->boolean('is_active')->default(true);

            /**
             * Timestamp standard Laravel.
             */
            $table->timestamps();

            /**
             * Indice per lookup rapidi.
             */
            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_payment_types');
    }
};
