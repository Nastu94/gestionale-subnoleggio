<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge campi per:
     * - Anagrafica estesa (ragione sociale)
     * - Licenza noleggio (boolean + dettagli)
     * - Cargos (password/PUK cifrati applicativamente)
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            /**
             * Ragione sociale ufficiale.
             * NB: manteniamo 'name' come nome display/operativo.
             */
            $table->string('legal_name', 191)
                ->nullable()
                ->after('name');

            /**
             * Licenza noleggio:
             * - rental_license: flag rapido per query/regole future
             * - number/expires_at: dettagli per validità (scadenze) e controlli
             */
            $table->boolean('rental_license')
                ->default(false)
                ->after('vat');

            $table->string('rental_license_number', 64)
                ->nullable()
                ->after('rental_license');

            $table->date('rental_license_expires_at')
                ->nullable()
                ->after('rental_license_number');

            /**
             * Cargos (password/PUK):
             * - usiamo TEXT per evitare problemi di lunghezza dovuti alla cifratura
             * - i valori verranno gestiti via cast "encrypted" nel Model
             */
            $table->text('cargos_password')
                ->nullable()
                ->after('is_active');

            $table->text('cargos_puk')
                ->nullable()
                ->after('cargos_password');
        });
    }

    /**
     * Ripristina lo schema precedente rimuovendo le colonne aggiunte.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'rental_license',
                'rental_license_number',
                'rental_license_expires_at',
                'cargos_password',
                'cargos_puk',
            ]);
        });
    }
};
