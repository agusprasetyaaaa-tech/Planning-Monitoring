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
        Schema::table('plans', function (Blueprint $table) {
            // Make manager_status and bod_status nullable
            // These should only be set when a report is submitted
            $table->string('manager_status')->nullable()->change();
            $table->string('bod_status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Revert back to NOT NULL (with default 'pending')
            $table->string('manager_status')->default('pending')->nullable(false)->change();
            $table->string('bod_status')->default('pending')->nullable(false)->change();
        });
    }
};
