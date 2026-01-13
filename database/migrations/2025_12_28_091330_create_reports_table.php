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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->date('execution_date');
            $table->string('location');
            $table->string('pic'); // Person In Charge
            $table->string('position'); // Division / Position
            $table->text('result_description');
            $table->text('next_plan_description')->nullable();
            $table->string('progress'); // 10%, 20%, etc.
            $table->boolean('is_success')->default(false); // Goal Achievement
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
