<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_provider_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('climate_location_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_job_id')->nullable();
            $table->string('status')->default('queued');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['climate_location_id', 'provider', 'period_start', 'period_end'], 'climate_provider_job_identity');
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_provider_jobs');
    }
};
