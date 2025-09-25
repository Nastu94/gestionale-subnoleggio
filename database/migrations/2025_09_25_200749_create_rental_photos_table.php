<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Foto associate al rental (pickup/return) */
    public function up(): void
    {
        Schema::create('rental_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('phase', ['pickup','return']);
            $table->string('label', 64)->nullable();
            $table->string('media_uuid', 64); // ref Media Library
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rental_id','phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_photos');
    }
};
