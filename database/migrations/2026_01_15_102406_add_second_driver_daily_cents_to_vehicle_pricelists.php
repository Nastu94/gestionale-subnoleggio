<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_pricelists', function (Blueprint $table) {
            $table->unsignedInteger('second_driver_daily_cents')
                ->nullable()
                ->after('base_daily_cents');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_pricelists', function (Blueprint $table) {
            $table->dropColumn('second_driver_daily_cents');
        });
    }
};
