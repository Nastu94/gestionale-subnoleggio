<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vehicle_pricelist_seasons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_pricelist_id')->constrained()->cascadeOnDelete();
            $t->string('name', 64);
            $t->char('start_mmdd', 5); // 'MM-DD'
            $t->char('end_mmdd', 5);   // 'MM-DD'
            $t->smallInteger('season_pct')->default(0); // +/- % sul daily
            $t->smallInteger('weekend_pct_override')->nullable(); // opzionale override weekend
            $t->tinyInteger('priority')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['vehicle_pricelist_id','is_active','priority'], 'vps_pl_active_prio_idx');
        });
    }
    public function down(): void {
        Schema::dropIfExists('vehicle_pricelist_seasons');
    }
};
