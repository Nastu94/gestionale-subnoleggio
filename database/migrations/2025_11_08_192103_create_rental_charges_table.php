<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Righe economiche di noleggio (base, extra, km_over, cleaning, damage, ecc.).
 * - Importi già IVA inclusa: un solo campo 'amount'
 * - Pagamento registrabile per singola riga (recorded/at/method)
 * - Calcolo commissione admin: somma 'amount' delle righe con is_commissionable = 1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_charges', function (Blueprint $table) {
            $table->id();

            // Relazione al noleggio
            $table->foreignId('rental_id')
                ->constrained('rentals')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /**
             * Tipologia riga (uso VARCHAR per flessibilità, niente ENUM rigido):
             * es: base, extra, km_over, service, cleaning, fuel_refill, damage, franchise, fine, discount, refund
             */
            $table->string('kind', 32);

            // Se true, la riga partecipa al totale commissionabile (Tᶜ)
            $table->boolean('is_commissionable')->default(true);

            // Testo libero per rendicontazione
            $table->string('description', 255)->nullable();

            // Importo riga già IVA inclusa (se usi quantity * unit_price, puoi valorizzare amount di conseguenza)
            $table->decimal('amount', 12, 2)->default(0.00);

            // Pagamento relativo alla riga (registrato sì/no, quando, come)
            $table->boolean('payment_recorded')->default(false);
            $table->timestamp('payment_recorded_at')->nullable();

            /**
             * Metodo pagamento: allineato ai valori che già usi su rentals
             * ('cash','pos','bank_transfer','other'). Lasciamo nullable: riga può non essere ancora saldata.
             */
            $table->enum('payment_method', ['cash','pos','bank_transfer','other'])->nullable();

            // Utente che ha creato la riga (non necessariamente chi incassa)
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indici utili
            $table->index(['rental_id', 'kind']);
            $table->index(['rental_id', 'is_commissionable']);
            $table->index(['payment_recorded', 'payment_method']);
            $table->index('payment_recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_charges');
    }
};
