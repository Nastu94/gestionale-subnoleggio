<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop dei campi legati al pagamento direttamente su rentals.
 *
 * ATTENZIONE: questa migration NON tocca `amount` (che potrebbe essere usato come
 * totale contrattuale). I pagamenti e i costi riga per riga vivono ora su `rental_charges`.
 */
return new class extends Migration
{
    /**
     * Esegue la rimozione dei campi pagamento dalla tabella rentals.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Rimuovo i soli campi "payment_*" attualmente spostati su rental_charges
            if (Schema::hasColumn('rentals', 'payment_recorded')) {
                $table->dropColumn('payment_recorded');
            }
            if (Schema::hasColumn('rentals', 'payment_recorded_at')) {
                $table->dropColumn('payment_recorded_at');
            }
            if (Schema::hasColumn('rentals', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('rentals', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
            if (Schema::hasColumn('rentals', 'payment_notes')) {
                $table->dropColumn('payment_notes');
            }
        });
    }

    /**
     * Ripristina i campi pagamento (rollback).
     * Allineati alle definizioni viste nello schema attuale:
     * - payment_method enum('cash','pos','bank_transfer','other') nullable
     * - gli altri campi nullable dove previsto
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // boolean tinyint(1)
            if (!Schema::hasColumn('rentals', 'payment_recorded')) {
                $table->boolean('payment_recorded')->default(false)->after('amount');
            }

            // datetime nullable
            if (!Schema::hasColumn('rentals', 'payment_recorded_at')) {
                $table->timestamp('payment_recorded_at')->nullable()->after('payment_recorded');
            }

            // enum nullable (coerente con lo schema attuale)
            if (!Schema::hasColumn('rentals', 'payment_method')) {
                $table->enum('payment_method', ['cash', 'pos', 'bank_transfer', 'other'])
                      ->nullable()
                      ->after('payment_recorded_at');
            }

            // varchar(128) nullable
            if (!Schema::hasColumn('rentals', 'payment_reference')) {
                $table->string('payment_reference', 128)->nullable()->after('payment_method');
            }

            // text nullable
            if (!Schema::hasColumn('rentals', 'payment_notes')) {
                $table->text('payment_notes')->nullable()->after('payment_reference');
            }
        });
    }
};
