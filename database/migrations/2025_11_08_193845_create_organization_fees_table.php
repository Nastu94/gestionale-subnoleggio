<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella delle fee admin per Organization (renter), con storico di validità.
 * - Una sola fee attiva per data (controllo applicativo).
 * - percent = percentuale fissa da applicare alla base commissionabile Tᶜ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_fees', function (Blueprint $table) {
            $table->id();

            // Organization a cui si applica la fee (renter)
            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Percentuale fissa (0..100 con 2 decimali)
            $table->decimal('percent', 5, 2);

            // Finestra di validità (chiusa o aperta)
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            // Facoltativo: tracciabilità
            $table->foreignId('created_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indici utili
            $table->index(['organization_id', 'effective_from']);
            $table->index(['organization_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_fees');
    }
};
