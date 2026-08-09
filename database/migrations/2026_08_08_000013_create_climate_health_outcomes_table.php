<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_health_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('climate_location_id')->constrained()->cascadeOnDelete();
            $table->string('indicator');
            $table->string('age_group')->default('under_5');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('value', 12, 2);
            $table->string('unit', 50);
            $table->string('source', 120);
            $table->string('quality_flag', 40)->default('reported');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['indicator', 'period_start', 'period_end']);
            $table->index(['climate_location_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_health_outcomes');
    }
};
