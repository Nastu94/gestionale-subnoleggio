<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Affidamenti Admin → Noleggiatore */
    public function up(): void
    {
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('renter_org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable(); // NULL = aperto
            $table->enum('status', ['scheduled','active','ended','revoked'])->default('scheduled');
            $table->unsignedInteger('mileage_start')->nullable();
            $table->unsignedInteger('mileage_end')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id','start_at','end_at']);
            $table->index(['renter_org_id','start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};
