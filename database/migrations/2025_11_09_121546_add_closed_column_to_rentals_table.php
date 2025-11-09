<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (!Schema::hasColumn('rentals','closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('rentals','closed_by')) {
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete()->after('closed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals','closed_by')) {
                $table->dropConstrainedForeignId('closed_by');
            }
            if (Schema::hasColumn('rentals','closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
