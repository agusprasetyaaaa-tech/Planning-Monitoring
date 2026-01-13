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
            // Lifecycle status yang lebih komprehensif
            $table->string('lifecycle_status')->default('active')->after('bod_status')->index();

            // Timestamps helper untuk lock logic
            $table->timestamp('submitted_at')->nullable()->after('lifecycle_status');
            $table->timestamp('manager_reviewed_at')->nullable()->after('submitted_at');
            $table->timestamp('bod_reviewed_at')->nullable()->after('manager_reviewed_at');
            $table->timestamp('expired_at')->nullable()->after('bod_reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'lifecycle_status',
                'submitted_at',
                'manager_reviewed_at',
                'bod_reviewed_at',
                'expired_at'
            ]);
        });
    }
};
