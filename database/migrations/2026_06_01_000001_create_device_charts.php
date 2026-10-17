<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // More chart styles (area, step, scatter, gauge, rose) need a wider column than line | bar.
        Schema::table('measurement_types', fn (Blueprint $t) => $t->string('chart_type', 10)->default('line')->change());
        Schema::table('device_measurement_type', fn (Blueprint $t) => $t->string('chart_type', 10)->nullable()->change());

        // The charts an owner arranged for a device. A device without rows shows one default chart per dataset.
        Schema::create('device_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_type_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('line');
            $table->string('title', 80)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['device_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_charts');
    }
};
