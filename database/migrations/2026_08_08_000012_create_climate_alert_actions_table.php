<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_alert_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('climate_alert_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('actor_name')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique('climate_alert_id');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_alert_actions');
    }
};
