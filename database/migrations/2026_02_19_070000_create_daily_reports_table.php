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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');

            $table->date('report_date');
            $table->string('activity_type');
            $table->text('description')->nullable();

            // Technical details (from planning reports)
            $table->string('location');
            $table->string('pic'); // Person In Charge
            $table->string('position'); // Division / Position
            $table->text('result_description');
            $table->string('progress'); // 10%, 20%, etc.
            $table->boolean('is_success')->default(false);

            $table->text('next_plan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
