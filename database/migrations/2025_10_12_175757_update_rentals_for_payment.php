<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Delta: aggiunge stati draft/closed e campi pagamento "memoriale".
 * - Non rinomina campi esistenti.
 * - Mantiene compatibilità con dati già presenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Campi pagamento "memoriale" (solo tracciamento)
        Schema::table('rentals', function (Blueprint $table) {
            if (!Schema::hasColumn('rentals','payment_recorded')) {
                $table->boolean('payment_recorded')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('rentals','payment_recorded_at')) {
                $table->dateTime('payment_recorded_at')->nullable()->after('payment_recorded');
            }
            if (!Schema::hasColumn('rentals','payment_method')) {
                // Enum per uniformità nelle select della UI
                $table->enum('payment_method', ['cash','pos','bank_transfer','other'])->nullable()->after('payment_recorded_at');
            }
            if (!Schema::hasColumn('rentals','payment_reference')) {
                $table->string('payment_reference', 128)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('rentals','payment_notes')) {
                $table->text('payment_notes')->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        // Rimuove i campi aggiunti
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals','payment_notes'))      $table->dropColumn('payment_notes');
            if (Schema::hasColumn('rentals','payment_reference'))  $table->dropColumn('payment_reference');
            if (Schema::hasColumn('rentals','payment_method'))     $table->dropColumn('payment_method');
            if (Schema::hasColumn('rentals','payment_recorded_at'))$table->dropColumn('payment_recorded_at');
            if (Schema::hasColumn('rentals','payment_recorded'))   $table->dropColumn('payment_recorded');
        });
    }
};
