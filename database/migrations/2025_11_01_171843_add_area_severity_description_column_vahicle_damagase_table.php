<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aggiunge i campi necessari a definire un danno creato
     * senza passare da un rental_damages:
     *  - source: origine del record (rental|manual|inspection|service)
     *  - area / severity / description: tipologia del danno (solo per source ≠ rental)
     *
     * Nota: quando first_rental_damage_id è valorizzato, questi campi
     *       possono rimanere NULL (li leggeremo dal rental_damage).
     */
    public function up(): void
    {
        // 1) Aggiungi colonne solo se mancano (la migrazione precedente è fallita a metà)
        Schema::table('vehicle_damages', function (Blueprint $table) {
            // Origine del danno (default 'rental')
            if (!Schema::hasColumn('vehicle_damages', 'source')) {
                $table->enum('source', ['rental','manual','inspection','service'])
                    ->default('rental')
                    ->after('last_rental_damage_id');
            }

            // Tipologia “manuale” (solo per source != rental)
            if (!Schema::hasColumn('vehicle_damages', 'area')) {
                $table->string('area', 64)->nullable()->after('source');
            }
            if (!Schema::hasColumn('vehicle_damages', 'severity')) {
                $table->enum('severity', ['low','medium','high'])->nullable()->after('area');
            }
            if (!Schema::hasColumn('vehicle_damages', 'description')) {
                $table->text('description')->nullable()->after('severity');
            }
        });

        // 2) Indice su (vehicle_id, source) solo se non esiste già
        $dbName = DB::getDatabaseName();
        $idxExists = DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', 'vehicle_damages')
            ->where('index_name', 'vehicle_damages_vehicle_source_idx')
            ->exists();

        if (!$idxExists) {
            Schema::table('vehicle_damages', function (Blueprint $table) {
                $table->index(['vehicle_id', 'source'], 'vehicle_damages_vehicle_source_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop indice se esiste
        $dbName = DB::getDatabaseName();
        $idxExists = DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', 'vehicle_damages')
            ->where('index_name', 'vehicle_damages_vehicle_source_idx')
            ->exists();

        if ($idxExists) {
            Schema::table('vehicle_damages', function (Blueprint $table) {
                $table->dropIndex('vehicle_damages_vehicle_source_idx');
            });
        }

        // Drop colonne se esistono (ordine inverso non è critico qui)
        Schema::table('vehicle_damages', function (Blueprint $table) {
            foreach (['description','severity','area','source'] as $col) {
                if (Schema::hasColumn('vehicle_damages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

};
