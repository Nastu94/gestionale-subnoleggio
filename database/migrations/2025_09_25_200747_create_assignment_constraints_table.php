<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Vincoli opzionali per affidamento (km max, geo-fence, etc.) */
    public function up(): void
    {
        Schema::create('assignment_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('vehicle_assignments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('max_km')->nullable();
            $table->unsignedTinyInteger('min_driver_age')->nullable();
            $table->json('allowed_drivers')->nullable();
            $table->json('geo_fence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_constraints');
    }
};
