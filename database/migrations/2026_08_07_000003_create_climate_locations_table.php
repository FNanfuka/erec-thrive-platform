<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('point');
            $table->string('country_code', 2)->nullable();
            $table->string('admin_level')->nullable();
            $table->string('external_id')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'admin_level']);
            $table->unique(['type', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_locations');
    }
};
