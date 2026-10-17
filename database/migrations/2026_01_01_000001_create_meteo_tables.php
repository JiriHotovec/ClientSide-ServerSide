<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('unit', 20);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('location', 100)->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->string('ingest_token_hash', 64)->nullable()->unique();
            $table->string('source_url', 500)->nullable();
            $table->json('source_mapping')->nullable();
            $table->unsignedSmallInteger('fetch_interval_min')->default(15);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('device_measurement_type', function (Blueprint $table) {
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['device_id', 'measurement_type_id']);
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_type_id')->constrained()->cascadeOnDelete();
            $table->double('value')->nullable(); // null = missing, never 0
            $table->dateTime('measured_at');
            $table->string('source', 20); // ingest | pull | seed
            $table->timestamps();
            $table->unique(['device_id', 'measurement_type_id', 'measured_at'], 'measurements_unique_point');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('device_measurement_type');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('measurement_types');
    }
};
