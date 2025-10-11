<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vehicle_pricelist_tiers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_pricelist_id')->constrained()->cascadeOnDelete();
            $t->string('name', 64)->nullable();
            $t->unsignedSmallInteger('min_days')->default(1);
            $t->unsignedSmallInteger('max_days')->nullable(); // null = infinito
            $t->unsignedInteger('override_daily_cents')->nullable(); // se presente, sostituisce il daily
            $t->smallInteger('discount_pct')->nullable(); // altrimenti sconto % sul subtotale
            $t->tinyInteger('priority')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['vehicle_pricelist_id','is_active','priority'], 'vpt_pl_active_prio_idx');
        });
    }
    public function down(): void {
        Schema::dropIfExists('vehicle_pricelist_tiers');
    }
};
