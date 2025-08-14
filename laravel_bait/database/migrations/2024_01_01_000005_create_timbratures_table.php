<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('timbratures', function (Blueprint $table) {
            $table->id();
            $table->string('tecnico');
            $table->string('cliente')->nullable();
            $table->datetime('ora_inizio')->nullable();
            $table->datetime('ora_fine')->nullable();
            $table->decimal('ore', 8, 2)->nullable();
            $table->string('file_source')->nullable();
            $table->string('processing_batch_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tecnico', 'ora_inizio']);
            $table->index(['cliente', 'ora_inizio']);
            $table->index('processing_batch_id');

            // Foreign key
            $table->foreign('tecnico')->references('name')->on('technicians')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timbratures');
    }
};