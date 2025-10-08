<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vehicle_pricelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            // Renter proprietario del listino (dedotto dall'assegnazione corrente in UI;
            // qui lo persistiamo per storicità e coerenza)
            $table->foreignId('renter_org_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();

            $table->string('name', 128)->nullable();
            $table->char('currency', 3)->default('EUR');

            $table->unsignedInteger('base_daily_cents');      // es. 3500 = €35,00
            $table->unsignedSmallInteger('weekend_pct')->default(0); // +% sab/dom

            $table->unsignedSmallInteger('km_included_per_day')->nullable(); // es. 100
            $table->unsignedInteger('extra_km_cents')->nullable(); // es. 20 = €0,20

            $table->unsignedInteger('deposit_cents')->nullable();  // cauzione (opz.)

            $table->enum('rounding', ['none','up_1','up_5'])->default('none');
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['vehicle_id','renter_org_id','is_active'], 'uniq_vehicle_renter_active');
            $table->index(['vehicle_id','renter_org_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('vehicle_pricelists');
    }
};
