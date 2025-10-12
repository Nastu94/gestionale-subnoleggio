<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Sub-noleggi (Renter → Cliente) */
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete(); // renter
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained('vehicle_assignments')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();

            $table->dateTime('planned_pickup_at');
            $table->dateTime('planned_return_at');
            $table->dateTime('actual_pickup_at')->nullable();
            $table->dateTime('actual_return_at')->nullable();

            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('return_location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->enum('status', ['reserved','checked_out','in_use','checked_in','cancelled','no_show', 'draft', 'closed'])->default('reserved');

            // denormalizzazioni utili per report/filtri veloci
            $table->unsignedInteger('mileage_out')->nullable();
            $table->unsignedInteger('mileage_in')->nullable();
            $table->unsignedTinyInteger('fuel_out_percent')->nullable();
            $table->unsignedTinyInteger('fuel_in_percent')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id','planned_pickup_at','planned_return_at']);
            $table->index(['organization_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
