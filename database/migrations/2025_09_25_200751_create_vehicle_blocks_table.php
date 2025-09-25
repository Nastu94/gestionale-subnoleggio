<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Blocchi calendario (Admin o Renter) — manutenzioni, fermi, custom */
    public function up(): void
    {
        Schema::create('vehicle_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete(); // chi crea: admin o renter
            $table->enum('type', ['maintenance','legal_hold','custom_block'])->default('custom_block');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['scheduled','active','ended','cancelled'])->default('scheduled');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id','start_at','end_at']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_blocks');
    }
};
