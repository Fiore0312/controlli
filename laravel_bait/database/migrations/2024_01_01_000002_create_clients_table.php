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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('zone_type', ['CENTRAL_MILAN', 'PERIPHERY', 'INDUSTRIAL'])->default('CENTRAL_MILAN');
            $table->boolean('is_same_group')->default(false);
            $table->string('group_identifier')->nullable();
            $table->decimal('distance_from_headquarters', 8, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['name', 'active']);
            $table->index(['group_identifier', 'is_same_group']);
            $table->index('zone_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};