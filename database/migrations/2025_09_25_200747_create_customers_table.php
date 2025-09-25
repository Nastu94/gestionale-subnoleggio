<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Clienti finali del noleggiatore */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 191);
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->enum('doc_id_type', ['id','passport','license','other'])->nullable();
            $table->string('doc_id_number', 64)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('address_line', 191)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id','name']);
            $table->unique(['organization_id','doc_id_number']); // commenta se non vuoi unicità
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
