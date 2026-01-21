<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {

            // Documento di identità (CI / Passaporto)
            $table->string('identity_document_type_code', 10)
                ->nullable()
                ->after('doc_id_type')
                ->comment('Codice CARGOS tipo documento identità');

            // Patente
            $table->unsignedSmallInteger('driver_license_document_type_code')
                ->nullable()
                ->after('driver_license_expires_at')
                ->comment('Codice CARGOS tipo documento patente');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'identity_document_type_code',
                'driver_license_document_type_code',
            ]);
        });
    }
};
