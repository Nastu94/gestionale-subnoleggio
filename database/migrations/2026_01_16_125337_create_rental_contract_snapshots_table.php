<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabella che conserva lo snapshot contrattuale (freeze-once).
     */
    public function up(): void
    {
        Schema::create('rental_contract_snapshots', function (Blueprint $table) {
            $table->id();

            /**
             * Collegamento 1:1 con rentals.
             * Usiamo unique() per garantire "freeze once" a livello DB.
             */
            $table->foreignId('rental_id')
                ->constrained('rentals')
                ->cascadeOnDelete();

            /**
             * Snapshot pricing congelato.
             * Struttura JSON: uguale a quella che oggi salvi in custom_properties['pricing_snapshot'].
             */
            $table->json('pricing_snapshot');

            /**
             * Facoltativo: chi ha “congelato” lo snapshot (non è audit completo, ma torna utile).
             * Se non ti serve, puoi lasciarlo e non usarlo mai.
             */
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // ✅ Freeze-once: un solo snapshot per rental
            $table->unique('rental_id');
        });
    }

    /**
     * Drop tabella.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_contract_snapshots');
    }
};
