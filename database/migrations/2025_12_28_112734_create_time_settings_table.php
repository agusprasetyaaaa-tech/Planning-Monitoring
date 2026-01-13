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
        Schema::create('time_settings', function (Blueprint $table) {
            $table->id();

            // Daily Report Configuration
            $table->string('daily_report_time_unit')->default('Days (Production)');
            $table->integer('daily_report_warning_threshold')->default(14);
            $table->integer('daily_report_critical_threshold')->default(30);

            // Planning Report Configuration
            $table->string('planning_time_unit')->default('Days (Production)');
            $table->integer('planning_warning_threshold')->default(14);
            $table->integer('plan_expiry_value')->default(7);
            $table->string('plan_expiry_unit')->default('Days (Production)');

            // Allowed Days (JSON for multiple days)
            $table->json('allowed_plan_creation_days')->nullable();

            // Testing Mode
            $table->boolean('testing_mode')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_settings');
    }
};
