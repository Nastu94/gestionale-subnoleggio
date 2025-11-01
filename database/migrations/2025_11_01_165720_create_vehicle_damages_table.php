<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabella vehicle_damages.
     *
     * Scelte progettuali:
     * - first_rental_damage_id = origine del danno (prima segnalazione): usiamo le sue foto fino a riparazione.
     * - last_rental_damage_id  = ultima ricognizione del danno (es. return successivo).
     * - is_open                = danno aperto/irrisolto (per preload in checklist pickup).
     * - fixed_at/repair_cost   = tracciamento riparazione.
     */
    public function up(): void
    {
        Schema::create('vehicle_damages', function (Blueprint $table) {
            $table->id();

            // Veicolo a cui è associato il danno
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();

            // Primo rental_damage che ha introdotto il danno (fonte delle foto)
            $table->foreignId('first_rental_damage_id')
                ->nullable()
                ->constrained('rental_damages')
                ->nullOnDelete();

            // Ultimo rental_damage che lo ha confermato/aggiornato
            $table->foreignId('last_rental_damage_id')
                ->nullable()
                ->constrained('rental_damages')
                ->nullOnDelete();

            // Stato operativo del danno
            $table->boolean('is_open')->default(true);  // true = ancora presente / non riparato

            // Dati riparazione
            $table->timestamp('fixed_at')->nullable();      // quando è stato sistemato (se applicabile)
            $table->foreignId('fixed_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('repair_cost', 10, 2)->nullable(); // costo riparazione (se noto)

            // Note amministrative
            $table->string('notes', 255)->nullable();

            // Audit base
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indici utili
            $table->index(['vehicle_id', 'is_open']);      // preload veloce in pickup
            $table->index('last_rental_damage_id');

            // Evita di legare lo stesso rental_damage a più vehicle_damages
            $table->unique(['first_rental_damage_id'], 'vehicle_damages_first_rental_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_damages');
    }
};
