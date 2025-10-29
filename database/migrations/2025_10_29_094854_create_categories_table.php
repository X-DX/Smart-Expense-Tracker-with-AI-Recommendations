<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Return an anonymous class that extends Laravel's Migration class
// This is a common structure in Laravel migrations when you create them using artisan commands
return new class extends Migration
{
    /**
     * Run the migrations.
     * This method is automatically called when you run: php artisan migrate
     */
    public function up(): void
    {
        // Create a new database table named "categories"
        Schema::create('categories', function (Blueprint $table) {
            // Create an auto-incrementing "id" column (primary key)
            $table->id();

            // Create a "user_id" column and set it as a foreign key
            // ->constrained() automatically references the "id" column of the "users" table
            // ->cascadeOnDelete() means if a user is deleted, all related categories will also be deleted
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Create a "name" column to store the category name (string type)
            $table->string('name');

            // Create a "color" column to store color codes (e.g., "#3B82F6")
            // The default value is "#3B82F6" if no color is provided
            $table->string('color')->default('#3B82F6');
            $table->string('icon')->nullable();

            // Add "created_at" and "updated_at" timestamp columns automatically
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * This method is called when you roll back the migration using: php artisan migrate:rollback
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
