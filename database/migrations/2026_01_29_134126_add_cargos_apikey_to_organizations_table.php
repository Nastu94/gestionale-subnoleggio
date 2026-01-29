<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // allineiamo agli altri segreti: stringa “capiente”
            // la metto nullable perché: solo chi ha licenza e usa DB deve compilarla
            $table->text('cargos_apikey')->nullable()->after('cargos_puk');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('cargos_apikey');
        });
    }
};
