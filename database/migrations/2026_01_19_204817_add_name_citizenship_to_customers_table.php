<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {

            // Nome / Cognome separati (richiesto da CARGOS)
            $table->string('first_name', 100)
                ->nullable()
                ->after('organization_id');

            $table->string('last_name', 100)
                ->nullable()
                ->after('first_name');

            // Cittadinanza
            $table->string('citizenship', 100)
                ->nullable()
                ->after('country_code');

            $table->unsignedInteger('citizenship_cargos_code')
                ->nullable()
                ->after('citizenship')
                ->comment('Codice LUOGO CARGOS della cittadinanza (Provincia = ES)')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['citizenship_cargos_code']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'citizenship',
                'citizenship_cargos_code',
            ]);
        });
    }
};
