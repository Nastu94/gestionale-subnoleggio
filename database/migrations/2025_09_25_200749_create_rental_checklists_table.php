<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Checklist di ritiro e rientro (una per tipo) */
    public function up(): void
    {
        Schema::create('rental_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('type', ['pickup','return']);
            $table->unsignedInteger('mileage');                 // km rilevati
            $table->unsignedTinyInteger('fuel_percent');        // 0–100
            $table->enum('cleanliness', ['poor','fair','good','excellent'])->nullable();
            $table->boolean('signed_by_customer')->default(false);
            $table->boolean('signed_by_operator')->default(false);
            $table->string('signature_media_uuid', 64)->nullable();
            $table->json('checklist_json')->nullable();         // dotazioni, ruota, ecc.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rental_id','type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_checklists');
    }
};
