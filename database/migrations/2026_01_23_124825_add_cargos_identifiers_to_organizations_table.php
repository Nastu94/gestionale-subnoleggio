<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Campi CARGOS "fissi" per singola azienda/renter
            // (non sono segreti come password/PUK, ma sono comunque dati di accesso/logistica)
            $table->string('codice_utente_cargos', 80)
                ->nullable()
                ->after('cargos_puk')
                ->index();

            // Lo tengo string per non perdere eventuali zeri iniziali / formati non numerici
            $table->string('agenzia_id_cargos', 32)
                ->nullable()
                ->after('codice_utente_cargos')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['codice_utente_cargos']);
            $table->dropIndex(['agenzia_id_cargos']);

            $table->dropColumn([
                'codice_utente_cargos',
                'agenzia_id_cargos',
            ]);
        });
    }
};
