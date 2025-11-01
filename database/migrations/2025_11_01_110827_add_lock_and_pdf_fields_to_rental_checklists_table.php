<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge:
 * - lock persistente (Opzione B): locked_at, locked_by_user_id, locked_reason, signed_media_id
 * - stato PDF non firmato: last_pdf_payload_hash, last_pdf_media_id
 * - sostitutiva: replaces_checklist_id (self-FK per “annulla e sostituisce”)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_checklists', function (Blueprint $table) {
            // --- LOCK persistente ---
            $table->timestamp('locked_at')->nullable()->after('updated_at');
            $table->foreignId('locked_by_user_id')
                ->nullable()
                ->after('locked_at')
                ->constrained('users')
                ->nullOnDelete(); // se l'utente viene rimosso, non sblocchiamo la checklist
            $table->string('locked_reason', 64)
                ->nullable()
                ->after('locked_by_user_id'); // es: "customer_signed_pdf"

            // Media firmato (PDF/immagine) che ha causato il lock
            $table->foreignId('signed_media_id')
                ->nullable()
                ->after('locked_reason')
                ->constrained('media')         // tabella Spatie Media Library
                ->restrictOnDelete();          // il firmato NON deve essere cancellabile

            // --- Ultimo PDF NON firmato generato ---
            $table->char('last_pdf_payload_hash', 64)
                ->nullable()
                ->after('signed_media_id');    // SHA-256 del payload normalizzato
            $table->foreignId('last_pdf_media_id')
                ->nullable()
                ->after('last_pdf_payload_hash')
                ->constrained('media')
                ->nullOnDelete();              // il PDF bozza può essere rimosso

            // --- Checklist sostitutiva (annulla e sostituisce la precedente) ---
            $table->foreignId('replaces_checklist_id')
                ->nullable()
                ->after('last_pdf_media_id')
                ->constrained('rental_checklists')
                ->nullOnDelete();

            // Indici utili
            $table->index('locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('rental_checklists', function (Blueprint $table) {
            // Droppa prima i vincoli, poi le colonne
            if (Schema::hasColumn('rental_checklists', 'locked_by_user_id')) {
                $table->dropConstrainedForeignId('locked_by_user_id');
            }
            if (Schema::hasColumn('rental_checklists', 'signed_media_id')) {
                $table->dropConstrainedForeignId('signed_media_id');
            }
            if (Schema::hasColumn('rental_checklists', 'last_pdf_media_id')) {
                $table->dropConstrainedForeignId('last_pdf_media_id');
            }
            if (Schema::hasColumn('rental_checklists', 'replaces_checklist_id')) {
                $table->dropConstrainedForeignId('replaces_checklist_id');
            }

            if (Schema::hasColumn('rental_checklists', 'locked_at')) {
                $table->dropIndex(['locked_at']);
            }

            $cols = [
                'locked_at',
                'locked_reason',
                'signed_media_id',
                'last_pdf_payload_hash',
                'last_pdf_media_id',
                'replaces_checklist_id',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('rental_checklists', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
