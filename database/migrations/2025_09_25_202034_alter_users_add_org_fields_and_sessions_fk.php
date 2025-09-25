<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aggiunge i campi di dominio a 'users' e FK su 'sessions.user_id'.
     * NB: Assicurati che la tabella 'organizations' esista prima di eseguire questa migration.
     */
    public function up(): void
    {
        // users: organization_id, phone, is_active, soft deletes
        Schema::table('users', function (Blueprint $table) {
            // Se il progetto è nuovo, possiamo rendere NOT NULL.
            // Se hai già utenti seed/legacy, valuta ->nullable() e poi backfill.
            $table->foreignId('organization_id')
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('phone', 32)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('phone');

            // Aggiunge deleted_at se non presente
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // sessions: aggiungi vincolo FK su user_id (set null alla cancellazione utente)
        Schema::table('sessions', function (Blueprint $table) {
            // se esistesse già un vincolo, valuta prima un dropForeign(['user_id'])
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // rimuovi FK se esiste
            $table->dropForeign(['user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['phone', 'is_active', 'deleted_at']);
        });
    }
};
