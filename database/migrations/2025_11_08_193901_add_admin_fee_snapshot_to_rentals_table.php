<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot percentuale/importo fee admin salvati sul rental quando va in 'closed'.
 * Non modifica altri campi esistenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Percentuale fotografata (0..100, 2 decimali)
            if (!Schema::hasColumn('rentals', 'admin_fee_percent')) {
                $table->decimal('admin_fee_percent', 5, 2)->nullable()->after('id');
            }
            // Importo fotografato (2 decimali, IVA inclusa come da tue righe)
            if (!Schema::hasColumn('rentals', 'admin_fee_amount')) {
                $table->decimal('admin_fee_amount', 12, 2)->nullable()->after('admin_fee_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals', 'admin_fee_amount')) {
                $table->dropColumn('admin_fee_amount');
            }
            if (Schema::hasColumn('rentals', 'admin_fee_percent')) {
                $table->dropColumn('admin_fee_percent');
            }
        });
    }
};
