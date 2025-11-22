<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    // Stores the currently selected month for filtering dashboard data
    public $selectedMonth;
    // Stores the currently selected year for filtering dashboard data
    public $selectedYear;
    // Holds the total amount the user has spent in the selected month
    public $totalSpent;
    // Stores the user's budget for the selected month
    public $monthlyBudget;
    // Percentage of the monthly budget already used
    public $percentageUsed;
    // Breakdown of expenses grouped by category (for charts)
    public $expenseByCategory;
    // Stores the latest 5 expenses for the selected month
    public $recentExpenses;
    // Stores a 6-month comparison of spending (used for charts)
    public $monthlyComparison;
    // Stores the top 3 categories where the user spends the most
    public $topCategories;
    // Holds the count of recurring expenses for the user
    public $recurringExpenseCount;


    /**
     * Runs when the component is first loaded.
     * Sets default month/year and loads dashboard statistics.
     */
    public function mount()
    {
        $this->selectedMonth = now()->month; // Default to current month
        $this->selectedYear = now()->year; // Default to current year
        $this->loadDashboardData(); // Load all calculated dashboard data
    }

    /**
     * Main function that loads all dashboard metrics:
     * total spent, budget, charts, comparisons, top categories, etc.
     */
    public function loadDashboardData()
    {
        $userId = Auth::id(); // Get logged-in user's ID

        /** -----------------------------
         * 1. TOTAL SPENT IN THE MONTH
         * --------------------------------
         * Calculates the total amount spent by the user
         * in the selected month & year.
         */
        $this->totalSpent = Expense::forUser($userId)
            ->inMonth($this->selectedMonth, $this->selectedYear)
            ->sum('amount');

        /** -----------------------------
         * 2. MONTHLY BUDGET
         * --------------------------------
         * Fetch the budget for this month.
         * If no budget set → default to 0.
         */
        $budget = Budget::where('user_id', $userId)
            ->where('month', $this->selectedMonth)
            ->where('year', $this->selectedYear)
            ->first();

        $this->monthlyBudget = $budget ? $budget->amount : 0;

        /** -----------------------------
         * 3. PERCENTAGE OF BUDGET USED
         * --------------------------------
         * Avoids division by zero.
         */
        $this->percentageUsed = $this->monthlyBudget > 0 ? round(($this->totalSpent / $this->monthlyBudget) * 100, 1) : 0;

        /** -----------------------------
         * 4. EXPENSES GROUPED BY CATEGORY
         * --------------------------------
         * Used for category-wise charts.
         */
        $this->expenseByCategory = Expense::select('categories.name', 'categories.color', DB::raw('SUM(expenses.amount) as total'))
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.user_id', $userId)
            ->whereMonth('expenses.date', $this->selectedMonth)
            ->whereYear('expenses.date', $this->selectedYear)
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderBy('total', 'desc')
            ->get();

        /** -----------------------------
         * 5. RECENT EXPENSES (LAST 5)
         * --------------------------------
         * Shows quick snapshot of latest expenses.
         */
        $this->recentExpenses = Expense::with('category')
            ->forUser($userId)
            ->whereMonth('date', $this->selectedMonth)
            ->whereYear('date', $this->selectedYear)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        /** -----------------------------
         * 6. MONTHLY COMPARISON (LAST 6 MONTHS)
         * --------------------------------
         * Generates historical chart data for trends.
         */
        $this->monthlyComparison = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonths($i);
            $total = Expense::forUser($userId)
                ->inMonth($date->month, $date->year)
                ->sum('amount');

            $this->monthlyComparison->push([
                'month' => $date->format('M'),
                'total' => $total,
            ]);
        }

        /** -----------------------------
         * 7. TOP 3 SPENDING CATEGORIES
         * --------------------------------
         * Useful for highlighting where most money goes.
         */
        $this->topCategories = $this->expenseByCategory->take(3);

        /** -----------------------------
         * 8. COUNT OF RECURRING EXPENSES
         * --------------------------------
         * Displays number of subscriptions / repeated payments.
         */
        $this->recurringExpenseCount = Expense::forUser($userId)
            ->recurring()
            ->count();
    }

    /**
     * Runs automatically when selectedMonth changes.
     * Reloads all dashboard metrics.
     */
    public function updatedSelectedMonth()
    {
        $this->loadDashboardData();
    }

    /**
     * Runs automatically when selectedYear changes.
     */
    public function updatedSelectedYear()
    {
        $this->loadDashboardData();
    }

    /**
     * Moves the dashboard to the previous month.
     */
    public function previousMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }

    /**
     * Moves the dashboard to the next month.
     */
    public function nextMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->addMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }

    /**
     * Returns the dashboard view for Livewire.
     */
    public function render()
    {
        return view('livewire.dashboard');
    }
}
