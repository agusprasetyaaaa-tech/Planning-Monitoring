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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('project_name')->nullable();
            $table->date('planning_date');
            $table->string('activity_type');
            $table->text('description')->nullable();

            // Statuses
            $table->enum('status', ['created', 'reported'])->default('created');
            $table->enum('manager_status', ['pending', 'rejected', 'escalated', 'approved'])->default('pending');
            $table->enum('bod_status', ['pending', 'failed', 'success'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
