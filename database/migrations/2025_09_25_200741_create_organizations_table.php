<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Anagrafiche organizzazioni: Admin (proprietario parco) e Renter (noleggiatore) */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->enum('type', ['admin','renter']);
            $table->string('vat', 32)->nullable();               // P.IVA / CF
            $table->string('address_line', 191)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'name']);
            $table->unique('vat'); // opzionale: commenta se non vuoi unicità su VAT
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
