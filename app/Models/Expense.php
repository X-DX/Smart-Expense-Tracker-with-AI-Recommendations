<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    // ─── Traits ───
    use HasFactory, SoftDeletes;
    // HasFactory → allows creating model factories for seeding/testing
    // SoftDeletes → enables soft deletion (adds "deleted_at" column)

    // ─── Mass Assignable Attributes ───
    // Defines which fields can be mass-assigned when creating/updating records
    protected $fillable = [
        'user_id', // Foreign key linking expense to a user
        'category_id', // Optional link to category
        'amount', // Expense amount (e.g. 59.99)
        'title', // Short name or title of expense
        'description', // Optional detailed notes
        'date', // Date the expense occurred
        'type', // "one-time" or "recurring"
        'recurring_frequency', // Frequency for recurring expenses (daily/weekly/monthly/yearly)
        'recurring_start_date', // When the recurrence starts
        'recurring_end_date', // When the recurrence ends
        'parent_expense_id', // Links to the original recurring expense
        'is_auto_generated', // Marks if system auto-created the record
    ];

    // ─── Attribute Casting ───
    // Ensures proper data types when retrieving or setting values
    protected $casts = [
        'amount' => 'decimal:2', // Always two decimal places for money
        'date' => 'date', // Automatically converted to Carbon instance
        'recurring_start_date' => 'date', // Converts to Carbon (date object)
        'recurring_end_date' => 'date', // Converts to Carbon (date object)
        'is_auto_generated' => 'boolean', // Casts 0/1 to true/false
    ];

    // ─── Relationships ───

    // Each expense belongs to one user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Each expense belongs to one category (optional)
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // A recurring expense template may have many child expenses
    // This defines a self-referential relationship (Expense → Expense)
    public function parentExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    // Retrieve all expenses generated from this recurring template
    public function childExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }
    
    // ─── Query Scopes ───
    // Scopes allow you to reuse query filters conveniently

    // Scope: filter expenses belonging to a specific user
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Scope: filter only recurring expenses
    public function scopeRecurring($query)
    {
        return $query->where('type', 'recurring');
    }

    // Scope: filter only one-time expenses
    public function scopeOneTime($query)
    {
        return $query->where('type', 'one-time');
    }

    // Scope: filter expenses within a specific month and year
    public function scopeInMonth($query, $month, $year)
    {
        return $query->whereMonth('date', $month)
                    ->whereYear('date', $year);
    }

    // Scope: filter expenses within a specific date range
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    // ─── Helper Methods ───

    // Check if an expense is recurring
    public function isRecurring(): bool
    {
        return $this->type === 'recurring';
    }

    // Determine if the system should generate the next recurring instance
    public function shouldGenerateNextOccurrence(): bool
    {
        // Skip if not recurring
        if (!$this->isRecurring()) {
            return false;
        }

        // Stop generating if the recurrence end date has passed
        if ($this->recurring_end_date && now()->isAfter($this->recurring_end_date)) {
            return false;
        }
        // Otherwise, continue generating future occurrences
        return true;
    }

    // Calculate the next occurrence date for a recurring expense
    public function getNextOccurrenceDate(): ?\Carbon\Carbon
    {
        // Only applies to recurring expenses
        if (!$this->isRecurring()) {
            return null;
        }

        // Get the latest generated child expense (most recent instance)
        $lastChildExpense = $this->childExpenses()
            ->orderBy('date', 'desc')
            ->first();

        // If child exists, base next date on its date; otherwise start date
        $baseDate = $lastChildExpense ? $lastChildExpense->date : $this->recurring_start_date;

        // Determine next date based on frequency
        return match($this->recurring_frequency) {
            'daily' => $baseDate->copy()->addDay(),
            'weekly' => $baseDate->copy()->addWeek(),
            'monthly' => $baseDate->copy()->addMonth(),
            'yearly' => $baseDate->copy()->addYear(),
            default => null,
        };
    }
}
