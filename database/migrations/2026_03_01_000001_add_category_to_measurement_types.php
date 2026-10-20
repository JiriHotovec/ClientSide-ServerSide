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
            $table->string('category', 20)->default('other')->index();
        });

        DB::table('measurement_types')->whereIn('key', ['temperature', 'humidity', 'pressure', 'wind_speed'])->update(['category' => 'weather']);
        DB::table('measurement_types')->whereIn('key', ['co2'])->update(['category' => 'air']);
    }

    public function down(): void
    {
        Schema::table('measurement_types', fn (Blueprint $table) => $table->dropColumn('category'));
    }
};
