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
        Schema::table('plan_status_logs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('is_grace_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_status_logs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
