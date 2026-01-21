<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella: cargos_luoghi
 *
 * Master data ufficiale CARGOS (Polizia di Stato).
 * Contiene:
 * - Comuni italiani
 * - Stati esteri
 * - Luoghi cessati (storico)
 *
 * NOTE IMPORTANTI:
 * - Il campo "code" è la PRIMARY KEY ufficiale CARGOS (NO auto-increment)
 * - I dati vengono importati da CSV/Excel ufficiale
 * - Le righe NON vanno mai cancellate (solo disattivate)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos_luoghi', function (Blueprint $table) {
            /**
             * Codice luogo CARGOS.
             * È il valore che va inviato nelle API (LUOGO_COD).
             */
            $table->unsignedInteger('code')->primary();

            /**
             * Nome del luogo (comune o stato).
             * Serve per UI e debug, NON per l'invio.
             */
            $table->string('name');

            /**
             * Sigla provincia (se presente).
             * NULL per stati esteri.
             */
            $table->string('province_code', 5)->nullable();

            /**
             * Codice paese ISO (es. IT, FR, DE).
             * NULL per comuni italiani.
             */
            $table->string('country_code', 3)->nullable();

            /**
             * Flag: luogo italiano.
             * Utile per validazioni e filtri UI.
             */
            $table->boolean('is_italian')->default(true);

            /**
             * Flag di attivazione.
             * I luoghi cessati NON vanno eliminati.
             */
            $table->boolean('is_active')->default(true);

            /**
             * Riga originale del file ufficiale (opzionale).
             * Serve come paracadute in caso di cambi formato futuri.
             */
            $table->json('raw_payload')->nullable();

            /**
             * Timestamp standard Laravel.
             * created_at: import iniziale
             * updated_at: eventuali aggiornamenti ufficiali
             */
            $table->timestamps();

            /**
             * Indici utili per lookup frequenti.
             */
            $table->index('name');
            $table->index('province_code');
            $table->index('country_code');
            $table->index(['is_italian', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_luoghi');
    }
};
