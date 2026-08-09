<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('status')->default('running');
            $table->unsignedInteger('records_ingested')->default(0);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_runs');
    }
};
