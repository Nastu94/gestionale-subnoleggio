<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // tutti in centesimi, nullable
            $table->unsignedBigInteger('lt_rental_monthly_cents')->nullable()->after('mileage_current');
            $table->unsignedBigInteger('insurance_kasko_cents')->nullable()->after('lt_rental_monthly_cents');
            $table->unsignedBigInteger('insurance_rca_cents')->nullable()->after('insurance_kasko_cents');
            $table->unsignedBigInteger('insurance_cristalli_cents')->nullable()->after('insurance_rca_cents');
            $table->unsignedBigInteger('insurance_furto_cents')->nullable()->after('insurance_cristalli_cents');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'lt_rental_monthly_cents',
                'insurance_kasko_cents',
                'insurance_rca_cents',
                'insurance_cristalli_cents',
                'insurance_furto_cents',
            ]);
        });
    }
};
