<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('climate_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('variable');
            $table->decimal('value', 14, 4)->nullable();
            $table->string('unit')->nullable();
            $table->dateTime('observed_at');
            $table->string('quality_flag')->default('observed');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['climate_location_id', 'source', 'variable', 'observed_at'], 'climate_observations_identity');
            $table->index(['source', 'variable', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_observations');
    }
};
