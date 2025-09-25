<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Sedi/punti ritiro-consegna (Admin o Renter) */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 191);
            $table->string('address_line', 191)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
