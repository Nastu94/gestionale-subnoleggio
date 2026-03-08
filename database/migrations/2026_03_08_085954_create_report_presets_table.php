<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabella dei preset report salvati dall'admin.
     *
     * Nota:
     * - metrics/dimensions/filters sono JSON per supportare evoluzioni future
     *   senza dover cambiare schema.
     * - created_by serve per audit (chi ha creato/modificato cosa).
     */
    public function up(): void
    {
        Schema::create('report_presets', function (Blueprint $table) {
            $table->id();

            /**
             * Nome “umano” del preset (es. "Commissioni per mese", "Incassi per renter").
             */
            $table->string('name');

            /**
             * Descrizione opzionale (aiuta a capire cosa fa il preset).
             */
            $table->text('description')->nullable();

            /**
             * Tipo report (whitelist applicativa).
             * Esempi v1:
             * - commissions_by_closure
             * - cash_by_payment_date
             * - cash_by_closure_month
             */
            $table->string('report_type')->index();

            /**
             * Metriche selezionate (array di chiavi whitelisted).
             * Esempio: ["sum_admin_fee_amount","count_rentals_closed"]
             */
            $table->json('metrics');

            /**
             * Dimensioni (group by) selezionate (array di chiavi whitelisted).
             * Esempio: ["month","renter"]
             */
            $table->json('dimensions')->nullable();

            /**
             * Filtri del report (JSON strutturato).
             * Esempio:
             * {
             *   "date_from":"2026-03-01",
             *   "date_to":"2026-03-31",
             *   "organization_id": 12,
             *   "vehicle_id": 5,
             *   "payment_method": "card",
             *   "kind": "base",
             *   "is_commissionable": true
             * }
             */
            $table->json('filters')->nullable();

            /**
             * Tipo grafico/tabella preferito dal preset (opzionale).
             * Esempi: "table", "bar", "line"
             */
            $table->string('chart_type')->nullable();

            /**
             * Utente che ha creato il preset (audit).
             * In questo modulo l'uso è previsto per admin, ma lasciamo il vincolo DB pulito.
             */
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            /**
             * Soft delete per evitare cancellazioni irreversibili dei preset.
             */
            $table->softDeletes();

            /**
             * Evita duplicati “fastidiosi” per lo stesso utente.
             * Se vuoi nome globale unico, cambia in: $table->unique('name');
             */
            $table->unique(['created_by', 'name']);
        });
    }

    /**
     * Elimina la tabella.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_presets');
    }
};