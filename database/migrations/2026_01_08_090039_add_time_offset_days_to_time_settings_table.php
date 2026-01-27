<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('time_settings', function (Blueprint $table) {
            $table->integer('time_offset_days')->default(0)->after('testing_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_settings', function (Blueprint $table) {
            $table->dropColumn('time_offset_days');
        });
    }
};
