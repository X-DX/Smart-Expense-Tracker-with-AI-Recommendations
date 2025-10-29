<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    // ─── Mass Assignable Attributes ───
    // Defines which fields can be mass-assigned (e.g., via Category::create([...]))
    protected $fillable = [
        'user_id', // Foreign key linking the category to a specific user
        'name', // Name of the category (e.g., "Food", "Transport")
        'color', // Hex color code for UI display (e.g., "#3B82F6")
        'icon', // Optional icon identifier or path for the category
    ];

    // ─── Relationships ───

    // Each category belongs to a single user
    // Defines an inverse one-to-many relationship:
    // A user can have many categories, but a category belongs to one user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // A category can have many related expenses
    // Defines a one-to-many relationship:
    // One category → many Expense records
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // A category can also have many budgets assigned to it
    // One category → many Budget records
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    // ─── Custom Business Logic ───
    // Calculate the total spending for this category in a specific month and year
    // Returns the total sum of all expenses.amount where the expense date
    // matches the given month and year for this category
    public function getTotalSpentForMonth($month, $year): float
    {
        return $this->expenses() // Access related expenses via hasMany
            ->whereMonth('date', $month) // Filter by month (using MySQL MONTH(date))
            ->whereYear('date', $year) // Filter by year (using MySQL YEAR(date))
            ->sum('amount');  // Sum up all the matching expense amounts
    }
}
