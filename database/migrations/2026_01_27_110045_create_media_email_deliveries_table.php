<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella log invio documenti (Spatie Media) via email.
 *
 * Obiettivi:
 * - anti-duplicati: 1 riga per "documento logico" (model_type + model_id + collection_name)
 * - traccia primo invio, rigenerazioni, reinvii e ultimi errori
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_email_deliveries', function (Blueprint $table): void {
            $table->id();

            /**
             * Identità del "documento logico":
             * - model_type/model_id: owner del media (Rental, RentalChecklist, ecc.)
             * - collection_name: tipo documento (signatures, checklist_pickup_signed, ...)
             */
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('collection_name');

            /**
             * Dati recipient per audit (l’email può cambiare nel tempo).
             */
            $table->string('recipient_email');

            /**
             * Tracking media:
             * - first_media_id: il media della prima versione inviata
             * - current_media_id: l’ultima versione generata (anche se NON inviata)
             * - last_sent_media_id: l’ultima versione effettivamente inviata
             */
            $table->unsignedBigInteger('first_media_id')->nullable();
            $table->unsignedBigInteger('current_media_id')->nullable();
            $table->unsignedBigInteger('last_sent_media_id')->nullable();

            /**
             * Stato:
             * - pending: creato log, invio non ancora tentato
             * - sent: inviata l’ultima versione (current_media_id == last_sent_media_id)
             * - regenerated: nuova versione generata ma non inviata (current_media_id != last_sent_media_id)
             * - failed: ultimo tentativo fallito
             * - resend_requested: richiesto reinvio manuale della current_media_id
             */
            $table->string('status', 32)->default('pending');

            /**
             * Contatori e timestamp operativi.
             */
            $table->unsignedSmallInteger('send_attempts')->default(0);
            $table->unsignedSmallInteger('regenerations_count')->default(0);

            $table->timestamp('first_sent_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_regenerated_at')->nullable();

            /**
             * Reinvio manuale.
             */
            $table->timestamp('resend_requested_at')->nullable();

            /**
             * Ultimo errore (se presente).
             */
            $table->text('last_error_message')->nullable();

            $table->timestamps();

            // Anti-duplicati: una riga per documento logico
            $table->unique(['model_type', 'model_id', 'collection_name'], 'uq_media_email_doc');

            // Indici utili per backoffice / query
            $table->index(['status']);
            $table->index(['recipient_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_email_deliveries');
    }
};
