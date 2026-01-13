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
        Schema::create('plan_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('field'); // 'manager_status' or 'bod_status'
            $table->string('old_value')->nullable();
            $table->string('new_value');
            $table->boolean('is_grace_period')->default(false); // true if within grace period (doesn't count toward limit)
            $table->timestamps();

            // Indexes for faster lookups
            $table->index(['plan_id', 'field', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_status_logs');
    }
};
