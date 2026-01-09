<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge:
     * - second_driver_id: seconda guida (Customer) opzionale
     * - final_amount_override: prezzo finale forzato (opzionale)
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {

            /**
             * Seconda guida:
             * - nullable: può non esserci
             * - FK su customers
             * - se il customer viene eliminato, il riferimento viene azzerato
             */
            if (!Schema::hasColumn('rentals', 'second_driver_id')) {
                $table->foreignId('second_driver_id')
                    ->nullable()
                    ->constrained('customers')
                    ->nullOnDelete();
            }

            /**
             * Forzatura prezzo finale:
             * - nullable: se null usiamo il calcolato
             * - decimal: importo in EUR
             */
            if (!Schema::hasColumn('rentals', 'final_amount_override')) {
                $table->decimal('final_amount_override', 10, 2)->nullable();
            }
        });
    }

    /**
     * Rollback:
     * - rimuove FK e colonna second_driver_id
     * - rimuove final_amount_override
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {

            if (Schema::hasColumn('rentals', 'second_driver_id')) {
                $table->dropConstrainedForeignId('second_driver_id');
            }

            if (Schema::hasColumn('rentals', 'final_amount_override')) {
                $table->dropColumn('final_amount_override');
            }
        });
    }
};
