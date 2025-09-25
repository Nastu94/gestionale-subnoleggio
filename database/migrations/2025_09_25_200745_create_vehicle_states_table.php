<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Log stati del veicolo (available/assigned/rented/maintenance/blocked) */
    public function up(): void
    {
        Schema::create('vehicle_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('state', ['available','assigned','rented','maintenance','blocked']);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();   // NULL = stato corrente
            $table->string('reason', 191)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id','started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_states');
    }
};
