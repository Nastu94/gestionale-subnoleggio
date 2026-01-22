<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cargos_transmissions', function (Blueprint $table) {
            $table->id();

            // Collegamenti (se mancano, restano null ma logghi comunque l'evento)
            $table->foreignId('rental_id')->nullable()->constrained('rentals');
            $table->foreignId('agency_organization_id')->nullable()->constrained('organizations');
            $table->foreignId('operator_user_id')->nullable()->constrained('users');

            // check | send (per ora userai soprattutto check)
            $table->string('action', 10)->index();

            // Esito tecnico della pipeline (non solo "esito" CARGOS della riga)
            $table->boolean('ok')->default(false)->index();

            // Dove ha fallito: resolver | builder | formatter | api.token | api.check | api.send
            $table->string('stage', 30)->nullable()->index();

            // Hash del record fixed-width per capire se i dati sono cambiati dopo un check
            $table->string('request_hash', 64)->nullable()->index();
            $table->unsignedInteger('record_length')->nullable();

            // Preview NON sensibile (inizio record: tipicamente solo id+date). Se vuoi, puoi anche ometterla.
            $table->string('record_preview', 160)->nullable();

            // Record completo: SENSIBILE (contiene dati personali). Consiglio: salvarlo solo se abiliti un flag.
            $table->longText('record')->nullable();

            // Errori “umani” del builder/formatter
            $table->json('validation_errors')->nullable();

            // Risposta raw dell'API (array di righe con esito/transactionid/errore)
            $table->json('api_response')->nullable();

            // Messaggio eccezione (token KO, HTTP KO, ecc.)
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['rental_id', 'action', 'created_at'], 'idx_cargos_rental_action_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_transmissions');
    }
};
