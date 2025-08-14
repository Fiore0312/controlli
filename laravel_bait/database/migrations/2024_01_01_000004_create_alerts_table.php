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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->enum('severity', ['CRITICO', 'ALTO', 'MEDIO', 'BASSO']);
            $table->decimal('confidence_score', 5, 2);
            $table->enum('confidence_level', ['MOLTO_ALTA', 'ALTA', 'MEDIA', 'BASSA', 'MOLTO_BASSA']);
            $table->string('tecnico');
            $table->text('message');
            $table->string('category');
            $table->json('details')->nullable();
            $table->string('business_impact')->nullable();
            $table->json('suggested_actions')->nullable();
            $table->json('data_sources')->nullable();
            $table->string('processing_batch_id')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('false_positive')->default(false);
            $table->timestamps();

            // Indexes for performance and queries
            $table->index(['severity', 'is_resolved']);
            $table->index(['tecnico', 'created_at']);
            $table->index(['category', 'confidence_score']);
            $table->index(['created_at', 'severity']);
            $table->index('processing_batch_id');
            $table->index(['is_resolved', 'false_positive']);

            // Foreign key relationship
            $table->foreign('tecnico')->references('name')->on('technicians')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};