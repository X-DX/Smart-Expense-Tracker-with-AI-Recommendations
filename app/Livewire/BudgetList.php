<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BudgetList extends Component
{
    // ----------------------
    // Public Properties (Form State)
    // ----------------------
    // These are reactive Livewire properties bound to inputs in the Blade view.
    public $selectedMonth; // 📅 Stores the month currently selected by the user for filtering budgets
    public $selectedYear; // 📆 Stores the year currently selected by the user for filtering budgets

    // 🧩 Controls the visibility of the "Create Budget" modal form (true = visible, false = hidden)
    public $showCreateModel = false;

    // ----------------------
    // The mount() method runs automatically when the component is first loaded.
    // It initializes default values for month and year to the current date.
    // ----------------------
    public function mount(){
        $this->selectedMonth = now()->month; // 🗓️ Set the default selected month to the current month
        $this->selectedYear = now()->year; // Set the default selected year to the current year
    }

    // 🧮 The #[Computed] attribute (used in Livewire v3)
    // Marks the following method as a computed property — meaning its result
    // is automatically recalculated whenever its dependent data changes.

    // ----------------------
    // This function retrieves all budgets for the logged-in user 
    // based on the currently selected month and year. 
    // It also enriches each budget with computed fields such as spent amount, 
    // remaining amount, usage percentage, and over-budget status.
    // ----------------------
    #[Computed]
    public function budgets(){
        return Budget::with('category') // 🔗 Eager load the related 'category' for each budget to avoid N+1 queries
        ->where('user_id', Auth::user()->id) // 🔒 Only fetch budgets belonging to the authenticated user
        ->where('month', $this->selectedMonth) // 📅 Filter by the selected month (controlled by Livewire form state)
        ->where('year', $this->selectedYear) // 📆 Filter by the selected year (also from form state)
        ->get() // 📥 Retrieve the matching budget records as a collection
        ->map(function ($budget) { // 🔁 Loop through each budget record to compute additional dynamic properties
                // 💰 Calculate the total amount spent for this budget (likely from related expenses)
                $budget->spent = $budget->getSpentAmount();
                // 💵 Calculate how much budget remains after spending
                $budget->remaining = $budget->getRemainingAmount();
                // 📊 Calculate what percentage of the total budget has been used
                $budget->percentage = $budget->getPercentageUsed();
                // 🚨 Determine whether the user has exceeded the budget limit
                $budget->is_over = $budget->isOverBudget();

                // 🔄 Return the enriched budget object back into the collection
                return $budget;
            });
    }

    // ----------------------
    // This computed property calculates the total budget amount for the selected month and year.
    // It automatically updates whenever its dependent data (like budgets, selectedMonth, or selectedYear) changes.
    // ----------------------
    #[Computed]
    public function totalBudget(){
        // 🧮 Sum up the 'amount' field of all budget records returned by the budgets() method
        // → $this->budgets refers to the computed collection of budgets filtered by user, month, and year
        // → sum('amount') adds up all the budget amounts for that filtered list
        return $this->budgets->sum('amount');
    }

    #[Computed]
    public function totalSpent(){
        // 💸 Calculate the total amount spent across all budgets
        // → Sums up the 'spent' field from each budget in the computed budgets() collection
        // → Automatically updates when budgets, month, or year change
        return $this->budgets->sum('spent');
    }

    #[Computed]
    public function totalRemaining(){
        // 💰 Calculate the total remaining budget across all categories
        // → Adds up each budget's 'remaining' value (budget - spent)
        // → Helps determine how much the user still has available overall
        return $this->budgets->sum('remaining');
    }

    #[Computed]
    public function overallPercentage(){
        // 📊 Calculate the overall percentage of the total budget that has been spent
        // → Formula: (total spent ÷ total budget) × 100
        // → Rounded to 1 decimal place for a cleaner display
        // ⚠️ Prevent division by zero if no budgets exist or totalBudget = 0
        if ($this->totalBudget == 0) {
            return 0;
        }
        // ✅ Return the calculated spending percentage (e.g., 74.5%)
        return round(($this->totalSpent / $this->totalBudget) * 100,1);
    }

    #[Computed]
    public function categories(){
        // 🗂️ Retrieve all categories that belong to the currently authenticated user
        // → Filters by user_id to ensure user-specific data isolation
        // → Orders categories alphabetically by 'name' for a better UI experience
        // → Returns a collection of Category models
        return Category::where('user_id', Auth::user()->id)
                ->orderBy('name')
                ->get();
    }

    // ----------------------
    // This function shifts the currently selected month and year backward by one month.
    // It uses Carbon to handle date calculations cleanly and automatically adjusts the year when crossing boundaries (e.g., January → December of previous year).
    // ----------------------
    public function previousMonth(){
        // 🗓️ Create a Carbon date object representing the 1st day of the currently selected month/year
        // → Example: if selectedMonth = 5 and selectedYear = 2025, this creates "2025-05-01"
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonth();

        // 📅 Update component state to reflect the new month and year
        // → These reactive properties will trigger recomputation of budgets and all dependent computed properties
        $this->selectedMonth = $date->month; // Update to the previous month (1–12)
        $this->selectedYear = $date->year; // Update the year (automatically adjusted if month rolls back to December)
    }

    // ----------------------
    // This function advances the currently selected month and year forward by one month.
    // It uses Carbon to manage date calculations and automatically handles year transitions (e.g., December → January of the next year).
    // ----------------------
    public function nextMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->addMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }

    // ----------------------
    // This function resets the selected month and year to the current date.
    // It’s typically used when a user wants to quickly return to “this month” 
    // after navigating to previous or future months.
    // ----------------------
    public function setCurrentMonth(){
        // 🗓️ Set the selected month to the current month (1–12)
        // → Uses Laravel’s global `now()` helper (an instance of Carbon)
        $this->selectedMonth = now()->month;

        // 📆 Set the selected year to the current year (e.g., 2025)
        // → Keeps the component’s time context up to date
        $this->selectedYear = now()->year;
    }

    // ----------------------
    // This function deletes a specific budget record securely.
    // It ensures the budget exists, verifies ownership, performs deletion,
    // and provides user feedback through a session flash message.
    // ----------------------
    public function deleteBudget($budgetId){
        // 🔍 Retrieve the budget record from the database by its ID
        // → If no matching record is found, Laravel automatically throws a 404 (Not Found) exception
        $budget = Budget::findOrFail($budgetId);

        // 🔒 Authorization check — ensure that the budget belongs to the currently logged-in user
        // → Prevents users from deleting budgets that don’t belong to them
        if ($budget->user_id !== Auth::user()->id) {
            abort(403);
        }

        // 🗑️ Delete the budget record from the database
        // → This action is irreversible, so it should usually be confirmed via a modal or prompt in the UI
        $budget->delete();
        session()->flash('message','Budget deleted succssfully.');
    }

    
    public function render()
    {
        return view('livewire.budget-list',[
            'budgets'=> $this->budgets,
            'totalBudget'=> $this->totalBudget,
            'totalSpent'=> $this->totalSpent,
            'totalRemaining'=> $this->totalRemaining,
            'overallPercentage'=> $this->overallPercentage,
            'categories'=> $this->categories,
        ]);
    }
}
