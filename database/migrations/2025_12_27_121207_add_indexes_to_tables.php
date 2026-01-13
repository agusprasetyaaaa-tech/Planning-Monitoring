<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add indexes for faster searches and sorting
        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
            $table->index('email');
            $table->index('created_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('name');
            $table->index('created_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('company_name');
            $table->index('product_id');
            $table->index('marketing_sales_id');
            $table->index('created_at');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->index('name');
            $table->index('manager_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['email']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['company_name']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['marketing_sales_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
