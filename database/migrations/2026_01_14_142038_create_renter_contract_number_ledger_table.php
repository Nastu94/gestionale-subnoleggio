<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea tabella audit per la numerazione progressiva dei contratti per noleggiatore (organization_id).
     * - Insert-only (audit): non aggiorniamo mai i record, solo insert.
     * - In questa fase (B1) NON aggiungiamo vincoli unici: li metteremo dopo il backfill (fase enforce).
     */
    public function up(): void
    {
        Schema::create('renter_contract_number_ledger', function (Blueprint $table) {

            /** PK */
            $table->id();

            /**
             * Noleggiatore (renter) che possiede il progressivo.
             * Nota: niente cascade, perché le organizzazioni non vengono eliminate hard.
             */
            $table->foreignId('organization_id')
                ->constrained('organizations');

            /**
             * Contratto (rental) a cui è stato assegnato il numero.
             * Nota: niente cascade (i rentals non dovrebbero essere eliminati hard).
             */
            $table->foreignId('rental_id')
                ->constrained('rentals');

            /**
             * Numero progressivo assegnato per organization_id.
             * Esempio: per organization_id=10 -> 1,2,3,...
             */
            $table->unsignedInteger('number_id');

            /**
             * Utente che ha effettuato l'azione (wizard creazione).
             * - nullable: nei backfill storici possiamo lasciare null.
             * - nullOnDelete: se l'utente viene eliminato, preserviamo l'audit.
             */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /** Timestamps audit */
            $table->timestamps();

            /**
             * Indici (NON unici) per performance:
             * - recupero "ultimo numero" per organization
             * - lookup veloce per organization+rental
             */
            $table->index(['organization_id', 'number_id'], 'renter_contract_number_ledger_org_number_idx');
            $table->index(['organization_id', 'rental_id'], 'renter_contract_number_ledger_org_rental_idx');
        });
    }

    /**
     * Rollback: elimina la tabella audit.
     */
    public function down(): void
    {
        Schema::dropIfExists('renter_contract_number_ledger');
    }
};
