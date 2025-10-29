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
        // Create a new database table named "expenses"
        Schema::create('expenses', function (Blueprint $table) {
            // Create an auto-incrementing "id" column (primary key)
            $table->id();

            // Create a "user_id" foreign key column referencing the "users" table
            // ->constrained() assumes the table name "users" and references "id"
            // ->cascadeOnDelete() means if a user is deleted, their expenses are also deleted
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Create a "category_id" foreign key column referencing the "categories" table
            // ->nullable() allows expenses without a category
            // ->nullOnDelete() sets this column to NULL if the category is deleted
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Create a "amount" column to store expense amount
            // decimal(10, 2): total 10 digits, 2 after decimal (e.g. 99999999.99 max)
            $table->decimal('amount', 10, 2);

            // Create a "title" column for short expense titles or labels
            $table->string('title');

            // Create a "description" column for detailed notes
            // ->nullable() makes it optional
            $table->text('description')->nullable();

            // Create a "date" column to store the expense date
            $table->date('date');

            // Create a "type" column to specify if the expense is one-time or recurring
            // Uses ENUM for fixed allowed values
            // Default is 'one-time'
            $table->enum('type', ['one-time', 'recurring'])->default('one-time');

            // Recurring expense fields
            // Frequency of recurrence (daily, weekly, monthly, yearly)
            // ->nullable() since one-time expenses don’t need this
            $table->enum('recurring_frequency', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();

            // Start date of recurrence period
            $table->date('recurring_start_date')->nullable();

            // End date of recurrence period
            $table->date('recurring_end_date')->nullable();

            // Parent expense ID for auto-generated recurring entries
            // Self-referencing foreign key (points to another record in the same table)
            // ->nullable() since not all expenses have a parent
            // ->constrained('expenses') defines the self-reference
            // ->nullOnDelete() sets parent_expense_id to NULL if the parent expense is deleted
            $table->foreignId('parent_expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            // Boolean flag to mark system-generated recurring instances
            // Default is false (manually created)
            $table->boolean('is_auto_generated')->default(false);

            // ─── Standard Laravel timestamps ───
            // Adds "created_at" and "updated_at"
            $table->timestamps();

            // ─── Soft Deletes ───
            // Adds "deleted_at" column for soft deletion instead of hard delete
            $table->softDeletes();

            // Improve query performance for frequently searched columns
            // For example: finding user expenses by date
            $table->index(['user_id', 'date']);
            // Index for filtering expenses by user and type (e.g., recurring vs one-time)
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
