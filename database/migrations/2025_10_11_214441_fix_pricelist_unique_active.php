<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Aggiungi active_flag se manca (nullable)
        if (!Schema::hasColumn('vehicle_pricelists', 'active_flag')) {
            Schema::table('vehicle_pricelists', function (Blueprint $table) {
                $table->unsignedTinyInteger('active_flag')->nullable()->after('is_active');
            });
        }

        // 2) Allinea i dati: attive -> 1, altre -> NULL
        DB::table('vehicle_pricelists')->update(['active_flag' => null]);
        DB::table('vehicle_pricelists')->where('is_active', 1)->update(['active_flag' => 1]);

        // 3) Droppa il vecchio unique su is_active (se esiste, qualunque sia il nome)
        $idx = collect(DB::select("
            SELECT INDEX_NAME AS name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'vehicle_pricelists'
              AND non_unique = 0
        "))->pluck('name');

        $candidates = [
            'uniq_vehicle_renter_active',
            'vehicle_pricelists_vehicle_id_renter_org_id_is_active_unique',
            'vehicle_pricelists_vehicle_id_renter_org_id_is_active_index',
        ];

        $toDrop = $idx->first(fn ($n) => in_array($n, $candidates, true));
        if ($toDrop) {
            DB::statement("ALTER TABLE vehicle_pricelists DROP INDEX `$toDrop`");
        }

        // 4) Crea il nuovo unique su (vehicle_id, renter_org_id, active_flag) se non esiste
        $hasNew = DB::select("
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'vehicle_pricelists'
              AND index_name = 'uniq_vehicle_renter_activeflag'
            LIMIT 1
        ");
        if (! $hasNew) {
            Schema::table('vehicle_pricelists', function (Blueprint $table) {
                $table->unique(
                    ['vehicle_id','renter_org_id','active_flag'],
                    'uniq_vehicle_renter_activeflag'
                );
            });
        }

        // 5) (opzionale) assicurati che is_active abbia default 0
        DB::statement("ALTER TABLE vehicle_pricelists MODIFY is_active TINYINT(1) NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        // Ripristina il vecchio unique su is_active
        Schema::table('vehicle_pricelists', function (Blueprint $table) {
            // se esiste, elimina il nuovo
            try { $table->dropUnique('uniq_vehicle_renter_activeflag'); } catch (\Throwable $e) {}

            // ricrea il vecchio
            $table->unique(['vehicle_id','renter_org_id','is_active'], 'uniq_vehicle_renter_active');
        });

        // (facoltativo) non rimuovo la colonna active_flag per non perdere dati storici
        // se vuoi toglierla:
        // if (Schema::hasColumn('vehicle_pricelists','active_flag')) {
        //     Schema::table('vehicle_pricelists', fn (Blueprint $t) => $t->dropColumn('active_flag'));
        // }
    }
};
