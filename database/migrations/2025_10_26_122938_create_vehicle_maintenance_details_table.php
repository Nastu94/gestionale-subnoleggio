<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenance_details', function (Blueprint $table) {
            $table->id();

            // 1:1 con vehicle_states (solo per gli stati = 'maintenance')
            $table->foreignId('vehicle_state_id')
                ->constrained('vehicle_states')
                ->cascadeOnDelete()
                ->unique();

            // luogo/officina dove avviene la manutenzione (impostato all'apertura)
            $table->string('workshop', 128);

            // costo totale (impostato alla chiusura). In centesimi per evitare float.
            $table->unsignedInteger('cost_cents')->nullable();

            // opzionale, ma utile per futuro; default EUR come il resto del gestionale
            $table->char('currency', 3)->default('EUR');

            // eventuali note (facoltative)
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_details');
    }
};
