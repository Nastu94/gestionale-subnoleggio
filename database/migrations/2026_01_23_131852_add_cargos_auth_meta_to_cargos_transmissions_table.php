<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge metadati di autenticazione CARGOS ai log trasmissioni.
 *
 * NOTA SICUREZZA:
 * - Non memorizziamo password/PUK/token.
 * - Username/AgencyId: solo mascherati + hash (sha256).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargos_transmissions', function (Blueprint $table) {
            $table->string('auth_source', 20)->nullable()->after('stage'); // 'organization' | 'config' | null

            $table->string('auth_username_masked', 120)->nullable()->after('auth_source');
            $table->char('auth_username_hash', 64)->nullable()->after('auth_username_masked');

            $table->string('auth_agency_id_masked', 60)->nullable()->after('auth_username_hash');
            $table->char('auth_agency_id_hash', 64)->nullable()->after('auth_agency_id_masked');
        });
    }

    public function down(): void
    {
        Schema::table('cargos_transmissions', function (Blueprint $table) {
            $table->dropColumn([
                'auth_source',
                'auth_username_masked',
                'auth_username_hash',
                'auth_agency_id_masked',
                'auth_agency_id_hash',
            ]);
        });
    }
};
