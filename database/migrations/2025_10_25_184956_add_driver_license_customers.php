<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aggiunge ai clienti i campi per la patente:
     * - driver_license_number: numero patente
     * - driver_license_expires_at: data di scadenza patente
     *
     * NB: non modifichiamo l'enum esistente doc_id_type (id/passport/license/other).
     *     La restrizione a "Carta d'identità" e "Passaporto" verrà fatta a livello UI.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Numero patente (es. AA1234567) — facoltativo per compatibilità
            $table->string('driver_license_number', 64)
                ->nullable()
                ->after('doc_id_number');

            // Scadenza patente (YYYY-MM-DD) — facoltativo per compatibilità
            $table->date('driver_license_expires_at')
                ->nullable()
                ->after('driver_license_number');
        });
    }

    /**
     * Rollback: rimuove i campi aggiunti.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['driver_license_number', 'driver_license_expires_at']);
        });
    }
};
