<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Danni rilevati (pickup/return/during) */
    public function up(): void
    {
        Schema::create('rental_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('phase', ['pickup','return','during']);
            $table->string('area', 64)->nullable();  // es. front_bumper
            $table->enum('severity', ['low','medium','high']);
            $table->text('description')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable(); // stima, NON contabile
            $table->unsignedSmallInteger('photos_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rental_id','phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_damages');
    }
};
