<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Parco auto dell'Admin */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('vin', 17)->nullable();                 // telaio
            $table->string('plate', 16);                           // targa
            $table->string('make', 64);
            $table->string('model', 64);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 32)->nullable();
            $table->enum('fuel_type', ['petrol','diesel','hybrid','electric','lpg','cng'])->default('petrol');
            $table->enum('transmission', ['manual','automatic'])->default('manual');
            $table->unsignedTinyInteger('seats')->nullable();
            $table->string('segment', 32)->nullable();             // es. compact, suv
            $table->unsignedInteger('mileage_current')->nullable();
            $table->foreignId('default_pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('plate');
            $table->unique('vin');
            $table->index('admin_organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
