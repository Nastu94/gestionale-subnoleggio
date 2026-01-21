<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {

            // Luogo di nascita
            $table->string('birth_place', 191)->nullable()->after('birthdate');
            $table->unsignedInteger('birth_place_code')->nullable()->after('birth_place');

            // Documento identità
            $table->string('identity_document_place_code', 10)
                ->nullable()
                ->after('identity_document_type_code');

            // Patente
            $table->string('driver_license_place_code', 10)
                ->nullable()
                ->after('driver_license_document_type_code');

        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'birth_place',
                'birth_place_code',
                'identity_document_place_code',
                'driver_license_place_code',
            ]);
        });
    }
};
