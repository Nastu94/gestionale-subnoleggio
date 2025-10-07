<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Identificatori aggiuntivi (alcune installazioni li usano per tracking)
            $table->uuid('uuid')->nullable();
            $table->ulid('ulid')->nullable();

            // Relazione polimorfica verso il modello che “possiede” il media
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // Collection (es. "vehicle_photos"), nome logico e file name effettivo
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');

            // Metadati file e dischi
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');

            // JSON per manipolazioni, custom props, conversioni generate, responsive
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');

            // Ordinamento nella collection
            $table->unsignedInteger('order_column')->nullable();

            $table->timestamps();

            // Indice utile per le query polimorfiche
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};