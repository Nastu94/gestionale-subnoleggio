<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cargos_transmissions', function (Blueprint $table) {
            /**
             * dry_run: utile per distinguere preflight in dev/test da invii reali in production.
             */
            $table->boolean('dry_run')->default(false)->index()->after('ok');

            /**
             * linked_check_id: collega un SEND al CHECK OK che lo ha “abilitato”.
             * Self-FK sulla stessa tabella.
             */
            $table->unsignedBigInteger('linked_check_id')->nullable()->index()->after('operator_user_id');

            $table->foreign('linked_check_id')
                ->references('id')
                ->on('cargos_transmissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cargos_transmissions', function (Blueprint $table) {
            $table->dropForeign(['linked_check_id']);
            $table->dropColumn(['linked_check_id', 'dry_run']);
        });
    }
};
