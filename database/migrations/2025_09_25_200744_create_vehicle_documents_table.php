<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Documenti dei veicoli (assicurazione, libretto, revisione, ecc.) */
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('type', ['insurance','registration','inspection','green_card','ztl_permit','other']);
            $table->string('number', 64)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['valid','expiring','expired'])->default('valid');
            $table->string('media_uuid', 64)->nullable(); // riferimento a Media Library
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id','type']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
