<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_key')->unique();
            $table->foreignId('climate_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hazard');
            $table->string('severity');
            $table->string('status')->default('active');
            $table->string('title');
            $table->text('summary');
            $table->json('recommended_actions')->nullable();
            $table->string('source');
            $table->dateTime('triggered_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'hazard']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_alerts');
    }
};
