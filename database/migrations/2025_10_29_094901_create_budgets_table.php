<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a new database table named "budgets"
        Schema::create('budgets', function (Blueprint $table) {
            // Create an auto-incrementing "id" column (primary key)
            $table->id();

            // Create a "user_id" column and set it as a foreign key
            // ->constrained() automatically references the "id" column on the "users" table
            // ->cascadeOnDelete() means if the user is deleted, all related budget records are deleted as well
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Create a "category_id" column and set it as a foreign key
            // ->nullable() allows the field to be null (in case the budget isn't tied to a category)
            // ->constrained() references the "id" column on the "categories" table
            // ->nullOnDelete() means if the category is deleted, this field will automatically be set to NULL
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Create a "amount" column to store the budgeted money
            // decimal(10, 2) means up to 10 digits in total, with 2 digits after the decimal point
            $table->decimal('amount', 10, 2);

            // Create an integer column "month" to store the budget month (1–12)
            $table->integer('month');

            // Create an integer column "year" to store the budget year (e.g., 2025)
            $table->integer('year');
            $table->timestamps();

            // Add a unique constraint so that each user can only have one budget entry
            // per category, per month, and per year.
            // Prevents duplicate records like multiple budgets for the same month/category
            $table->unique(['user_id', 'category_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
