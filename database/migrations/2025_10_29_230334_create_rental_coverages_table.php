<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella: rental_coverages
 * Una riga per noleggio:
 *  - Flag coperture: rca, kasko, furto_incendio, cristalli, assistenza
 *  - Franchigie: valori in EUR (decimal(10,2)) — nullable se non definite
 *  - expected_km: chilometri previsti (opzionale) usati nel preventivo/contratto
 *
 * Nota:
 *  - rca è sempre true nel flusso, ma lo teniamo salvato per completezza.
 *  - FK su rentals(id), unique su rental_id per garantire 1:1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_coverages', function (Blueprint $table) {
            $table->id();

            // Collegamento 1:1 con il noleggio
            $table->foreignId('rental_id')
                ->constrained('rentals')
                ->cascadeOnDelete(); // se il rental viene eliminato, elimina anche le coperture

            // --- Coperture (flag booleani) ---
            $table->boolean('rca')->default(true);              // RCA obbligatoria
            $table->boolean('kasko')->default(false);
            $table->boolean('furto_incendio')->default(false);
            $table->boolean('cristalli')->default(false);
            $table->boolean('assistenza')->default(false);

            // --- Franchigie (EUR) ---
            // NB: decimal(10,2) per importi in euro; nullable se non impostate.
            $table->decimal('franchise_rca', 10, 2)->nullable();
            $table->decimal('franchise_kasko', 10, 2)->nullable();
            $table->decimal('franchise_furto_incendio', 10, 2)->nullable();
            $table->decimal('franchise_cristalli', 10, 2)->nullable();

            // Eventuali note libere (es. clausole personalizzate di pagamento/ass.)
            $table->text('notes')->nullable();

            $table->timestamps();

            // Garantisce una sola riga per rental
            $table->unique('rental_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_coverages');
    }
};
