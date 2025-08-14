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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('contratto')->nullable();
            $table->string('id_ticket')->nullable();
            $table->datetime('iniziata_il')->nullable();
            $table->datetime('conclusa_il')->nullable();
            $table->string('azienda')->nullable();
            $table->string('tipologia_attivita')->nullable();
            $table->text('descrizione')->nullable();
            $table->decimal('durata', 8, 2)->nullable();
            $table->string('creato_da')->nullable();
            $table->string('file_source')->nullable();
            $table->string('processing_batch_id')->nullable();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->boolean('is_validated')->default(false);
            $table->timestamps();

            // Indexes for performance
            $table->index(['creato_da', 'iniziata_il']);
            $table->index(['azienda', 'iniziata_il']);
            $table->index(['iniziata_il', 'conclusa_il']);
            $table->index('processing_batch_id');
            $table->index(['id_ticket', 'creato_da']);

            // Foreign key relationships
            $table->foreign('creato_da')->references('name')->on('technicians')->onDelete('set null');
            $table->foreign('azienda')->references('name')->on('clients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};