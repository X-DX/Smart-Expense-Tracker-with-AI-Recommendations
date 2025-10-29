<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    // ─── Mass Assignable Attributes ───
    // Defines which fields can be set through mass assignment
    protected $fillable = [
        'user_id', // ID of the user who owns this budget
        'category_id', // Optional link to a specific category
        'amount', // The total allocated budget amount
        'month', // Month this budget applies to (1–12)
        'year', // Year this budget applies to (e.g., 2025)
    ];

    // ─── Attribute Casting ───
    // Automatically converts fields to specific data types when accessed
    protected $casts = [
        'amount' => 'decimal:2', // Always display amount with 2 decimal places
        'month' => 'integer', // Ensure month is always treated as integer
        'year' => 'integer', // Ensure year is always treated as integer
    ];

    // ─── Relationships ───

    // A budget belongs to one user
    // Each user can have many budgets (one per category per month)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // A budget may belong to a specific category
    // Some budgets may be general (no category)
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // ─── Business Logic ───

    // Get the total amount spent for this budget (for its month and year)
    public function getSpentAmount(): float
    {
        // If the budget is linked to a category
        if ($this->category_id) {
            // Use the Category model’s helper method to calculate total spent
            return $this->category->getTotalSpentForMonth($this->month, $this->year);
        }

        // If no category, calculate the total spent across all expenses for this user
        return Expense::forUser($this->user_id) // Query scope for filtering by user
            ->inMonth($this->month, $this->year) // Query scope for filtering by month and year
            ->sum('amount'); // Sum up all matching expenses
    }

    // Calculate how much money is left in this budget
    public function getRemainingAmount(): float
    {
        // Remaining = Budget amount - Spent amount
        return $this->amount - $this->getSpentAmount();
    }

    // Calculate what percentage of the budget has been used
    public function getPercentageUsed(): float
    {
        // Avoid division by zero
        if ($this->amount == 0) {
            return 0;
        }
        // (Spent / Total Budget) * 100
        return ($this->getSpentAmount() / $this->amount) * 100;
    }

    // Check whether the budget has been exceeded
    public function isOverBudget(): bool
    {
        // Returns true if spent amount is greater than allocated amount
        return $this->getSpentAmount() > $this->amount;
    }
}
