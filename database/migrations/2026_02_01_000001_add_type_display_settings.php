<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_types', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->index(); // global (visible to everyone) vs private to its creator
            $table->string('unit_position', 6)->default('after');  // before | after | none
            $table->boolean('unit_space')->default(true);
            $table->unsignedTinyInteger('decimals')->default(1);
            $table->string('color', 7)->nullable();
            $table->string('chart_type', 4)->default('line');     // line | bar
            $table->double('y_min')->nullable();
            $table->double('y_max')->nullable();
        });

        DB::table('measurement_types')->where('is_system', true)->update(['is_public' => true]);

        // Per-device overrides: null = inherit from the measurement type.
        Schema::table('device_measurement_type', function (Blueprint $table) {
            $table->string('unit', 20)->nullable();
            $table->string('unit_position', 6)->nullable();
            $table->boolean('unit_space')->nullable();
            $table->unsignedTinyInteger('decimals')->nullable();
            $table->string('color', 7)->nullable();
            $table->string('chart_type', 4)->nullable();
            $table->double('y_min')->nullable();
            $table->double('y_max')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_measurement_type', fn (Blueprint $t) => $t->dropColumn(['unit', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max']));
        Schema::table('measurement_types', fn (Blueprint $t) => $t->dropColumn(['is_public', 'unit_position', 'unit_space', 'decimals', 'color', 'chart_type', 'y_min', 'y_max']));
    }
};
