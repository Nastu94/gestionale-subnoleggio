<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_pricelists', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_pricelists', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('id');
            }
            if (!Schema::hasColumn('vehicle_pricelists', 'status')) {
                $table->enum('status', ['draft','active','archived'])->default('draft')->after('version');
            }
            if (!Schema::hasColumn('vehicle_pricelists', 'active_flag')) {
                $table->boolean('active_flag')->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('vehicle_pricelists', 'notes')) {
                $table->string('notes', 255)->nullable()->after('rounding');
            }
            if (!Schema::hasColumn('vehicle_pricelists', 'published_at')) {
                $table->timestamp('published_at')->nullable()->change(); // se già esiste ok, altrimenti handled sotto
            }
        });

        // Normalize dati esistenti: mappa is_active -> status/active_flag
        // NB: se 'published_at' non esisteva, questa UPDATE ignorerà quel campo.
        DB::statement("
            UPDATE vehicle_pricelists
            SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'draft' END,
                active_flag = CASE WHEN is_active = 1 THEN 1 ELSE NULL END,
                version = COALESCE(version, 1)
        ");

        // vincolo unico: una sola 'active' per (vehicle, renter) usando active_flag = 1
        Schema::table('vehicle_pricelists', function (Blueprint $table) {
            $table->unique(['vehicle_id','renter_org_id','active_flag'], 'uniq_active_pl_per_vehicle_renter');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_pricelists', function (Blueprint $table) {
            $table->dropUnique('uniq_active_pl_per_vehicle_renter');
            if (Schema::hasColumn('vehicle_pricelists','active_flag')) {
                $table->dropColumn('active_flag');
            }
            if (Schema::hasColumn('vehicle_pricelists','status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('vehicle_pricelists','version')) {
                $table->dropColumn('version');
            }
            if (Schema::hasColumn('vehicle_pricelists','notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
