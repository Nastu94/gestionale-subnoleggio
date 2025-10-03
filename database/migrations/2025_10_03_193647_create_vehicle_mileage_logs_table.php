<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella di audit per gli aggiornamenti chilometraggio dei veicoli.
 * Traccia: veicolo, valore precedente, nuovo valore, utente, sorgente e timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_mileage_logs', function (Blueprint $table) {
            $table->id();

            // FK al veicolo (obbligatoria)
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            // Migliaggio prima/dopo (valori assoluti)
            $table->unsignedInteger('mileage_old')->nullable();
            $table->unsignedInteger('mileage_new');

            // Utente che ha effettuato il cambio (può essere null se utente cancellato o processi di sistema)
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Sorgente dell’aggiornamento (utile in futuro per import/API). Default: manual.
            $table->enum('source', ['manual', 'import', 'api'])->default('manual');

            // Note opzionali (es. motivo dell’aggiornamento)
            $table->string('notes', 255)->nullable();

            // Timestamp dell’evento (separato da created_at per maggiore chiarezza)
            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            // Indici utili
            $table->index(['vehicle_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_mileage_logs');
    }
};
