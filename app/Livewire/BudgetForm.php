<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetAIService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title(content: "Budget Form - ExpenseApp")]

class BudgetForm extends Component
{
    // ----------------------
    // These are Livewire public properties (form state variables) used by the BudgetForm component.
    // Each property represents a form field or UI state, and they are automatically reactive — 
    // meaning they stay in sync with the Blade view inputs in real time.
    // ----------------------
    public $budgetId; // Holds the ID of the budget being edited (null when creating a new one)
    public $amount = ''; // Stores the budget amount entered by the user (string to handle empty input cleanly)
    public $month; // Represents the selected month for the budget (integer: 1–12)
    public $year; // Represents the selected year for the budget (e.g., 2025)
    public $category_id = ''; // Stores the ID of the category this budget belongs to (used in dropdown selection)

    // Boolean flag to indicate whether the form is in "edit mode"  
    // → true = editing existing budget, false = creating a new one
    public $isEdit = false;

    // AI recommendation properties
    public $aiRecommendation = null;
    public $showAIRecommendation = false;
    public $loadingRecommendation = false;
    public $hasHistoricalData = false;

    protected function rules()
    {

        // ----------------------
        // This protected method defines all validation rules for the BudgetForm component.
        // It ensures that user inputs are valid, properly formatted, and unique
        // (so users can’t create multiple budgets for the same category, month, and year).
        // ----------------------
        // Base validation rules (apply to both create and edit modes)
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'category_id' => 'nullable|exists:categories,id',
        ];

        // ----------------------
        // Uniqueness Rule Logic
        // ----------------------
        // The goal: Prevent duplicate budgets for the same (user + category + month + year) combination.
        // Example: A user shouldn’t create two "Food" budgets for March 2025.
        // ----------------------
        $uniqueRule = 'unique:budgets,category_id,NULL,id,user_id,' . Auth::user()->id . ',month,' . $this->month . ',year,' . $this->year;

        // If we’re editing, exclude the current budget ID from the uniqueness check
        // → So it doesn’t block updating the same record.
        if ($this->isEdit) {
            $uniqueRule = 'unique:budgets,category_id,' . $this->budgetId . ',id,user_id,' . Auth::user()->id . ',month,' . $this->month . ',year,' . $this->year;
        }

        // Combine validation rules for 'category_id' dynamically
        // - If a category is selected → it must exist AND follow the uniqueness rule
        // - If not selected → it's nullable, but still checked for uniqueness (for "uncategorized" budgets)
        $rules['category_id'] = $this->category_id ? 'required|exists:categories,id|' . $uniqueRule : 'nullable|' . $uniqueRule;

        // Return the final set of rules for Livewire to validate before saving
        return $rules;
    }

    // ----------------------
    // Custom Validation Messages for BudgetForm
    // ----------------------
    // This property overrides Laravel’s default validation messages with clearer,
    // more user-friendly text. When a rule fails (defined in protected function rules()),
    // the corresponding key here defines what message to show.
    // ----------------------
    protected $messages = [
        'amount.required' => 'Please enter a budget amount.',
        'amount.min' => 'Budget amount must be greater than 0.',
        'month.required' => 'Please select a month.',
        'year.required' => 'Please select a year.',
        'category_id.unique' => 'You already have a budget for this category in this month.',
    ];

    // ----------------------
    // The mount() method runs automatically when the BudgetForm component is initialized.
    // It determines whether the form is being used to create a new budget or edit an existing one,
    // and sets up the initial form state accordingly.
    // ----------------------
    public function mount($budgetId = null)
    {
        // Check if a budget ID was passed to the component (from the route or parent component)
        // → If yes, the user is editing an existing budget.
        if ($budgetId) {
            $this->isEdit = true; // Enable edit mode
            $this->budgetId = $budgetId; // Store the ID of the budget being edited

            // Load the existing budget data from the database into the form fields
            // → This method (defined elsewhere) populates amount, month, year, and category_id
            $this->loadBudget();
        } else {
            // No budget ID means we’re creating a new budget
            // → Set default month and year to the current date for convenience
            $this->month = now()->month;
            $this->year = now()->year;
            $this->checkHistoricalData();
        }
    }

    public function updatedCategoryId()
    {
        $aiService = new BudgetAIService();
        $this->hasHistoricalData = $aiService->hasEnoughHistoricalData(
            $this->category_id ?: null,
            auth::id()
        );

        // reset the Ai recommendations
        $this->aiRecommendation = null;
        $this->showAIRecommendation = false;
    }

    /**
     * Check historical data when month/year changes
     */
    public function updatedMonth()
    {
        $this->checkHistoricalData();
    }

    public function updatedYear()
    {
        $this->checkHistoricalData();
    }

    // ----------------------
    // Load Existing Budget Data into the Form
    // ----------------------
    // This method is called (from mount()) when editing an existing budget.
    // It retrieves the budget record by its ID, ensures the logged-in user
    // has permission to access it, and populates the Livewire component’s
    // public properties so the form fields show the current values.
    // ----------------------
    public function loadBudget()
    {
        // Attempt to find the budget by ID (stored in $this->budgetId).
        // If no budget with that ID exists, Laravel automatically throws a 404 error.
        $budget = Budget::findOrFail($this->budgetId);

        // Authorization Check
        // Ensures the budget belongs to the currently logged-in user.
        // Prevents users from directly visiting URLs like /budgets/5/edit
        // and editing someone else’s budget data.
        if ($budget->user_id !== Auth::user()->id) {
            abort(403);
        }

        // Populate form fields with the existing budget data.
        // These public properties are bound to inputs in the Blade view,
        // so when they’re updated, the form automatically displays the current values.
        $this->amount = $budget->amount; // The budgeted amount for this category/month
        $this->month = $budget->month; // The month of the budget
        $this->year = $budget->year; // The year of the budget
        $this->category_id = $budget->category_id; // The linked category (or null if none)
    }

    // ----------------------
    // This function handles both creating and updating budgets.
    // It validates the input data, checks user authorization, performs the save/update,
    // flashes a success message, and redirects back to the budget list.
    // ----------------------
    public function save()
    {
        // 1. Validate form inputs using the validation rules defined in this component
        $this->validate();

        // 2. Prepare the data array for either creating or updating a budget record
        $data = [
            'user_id' => Auth::user()->id, // Associate the budget with the logged-in user
            'amount' => $this->amount, // The amount entered by the user
            'month' => $this->month, // Selected month for this budget
            'year' => $this->year, // Selected year for this budget
            'category_id' => $this->category_id ?: null, // Linked category (nullable in case user didn’t select one)
        ];

        // 3. Check if we are in edit mode (updating an existing budget)
        if ($this->isEdit) {
            // Find the existing budget by ID or fail if it doesn’t exist
            $budget = Budget::findOrFail($this->budgetId);

            // Security check: ensure the budget belongs to the logged-in user
            if ($budget->user_id !== Auth::user()->id) {
                abort(403);
            }

            $budget->update($data);
            session()->flash('message', 'Budget updated successfully.');
        } else {
            // 4. Create a new budget record when not in edit mode
            Budget::create($data);
            session()->flash('message', 'Budget created successfully.');
        }
        // 5. Redirect the user back to the budget list page after saving
        return redirect()->route('budgets.index');
    }

    // ----------------------
    // This computed property dynamically generates a collection of months (January–December)
    // It returns an array of key-value pairs containing:
    //  → 'value' → the numeric month (1–12)
    //  → 'name'  → the full month name ("January", "February", etc.)
    // This data is typically used to populate a <select> dropdown in your form.
    // ----------------------
    #[Computed]
    public function months()
    {
        // Create a collection of numbers from 1 to 12 → representing each month
        return collect(range(1, 12))->map(function ($month) {
            return [
                'value' => $month, // Numeric value for the month (used in dropdown values, e.g., 1 for January)

                // Convert the numeric month into its full name using Carbon (e.g., 1 → January)
                // Carbon::create(null, $month, 1) creates a date like "2025-$month-01"
                // ->format('F') outputs the full month name
                'name' => Carbon::create(null, $month, 1)->format('F'),
            ];
        });
    }

    // ----------------------
    // This computed property dynamically generates a list (collection) of years 
    // centered around the current year. 
    // It's typically used to populate a <select> dropdown so the user can pick 
    // the year for which they want to create or view a budget.
    // ----------------------
    #[Computed]
    public function years()
    {
        // Get the current year (e.g., 2025)
        $currentYear = now()->year;
        //  Create a collection of years starting from (current year - 1) to (current year + 2)
        // → Example: if current year is 2025 → [2024, 2025, 2026, 2027]
        return collect(range($currentYear - 1, $currentYear + 2));
    }

    // ----------------------
    // This computed property retrieves all budget categories that belong to the currently logged-in user.
    // It’s typically used to populate a category selection dropdown in the budget creation/edit form.
    // ----------------------
    #[Computed]
    public function categories()
    {
        return Category::where('user_id', Auth::user()->id) // Filter categories to only those created by the authenticated user
            ->orderBy('name') //  Sort categories alphabetically by name for user-friendly display
            ->get(); // Execute the query and return a collection of Category models
    }

    // Check if AI can be used based on user's historical data
    private function checkHistoricalData()
    {
        // Only run check when both month and year are selected
        if ($this->month && $this->year) {
            // Create an instance of the AI service
            $aiService = new BudgetAIService();
            // Ask service if this user has enough data in the last 3 months
            // If category is not selected, pass null (overall spending)
            $this->hasHistoricalData = $aiService->hasEnoughHistoricalData($this->category_id ?: null, Auth::user()->id);
        }
    }

    // Called when user clicks "Get AI Recommendation" button
    public function getAIRecommendation()
    {
        // Show loading indicator in the UI
        $this->loadingRecommendation = true;

        try {
            // Create AI service instance
            $aiService = new BudgetAIService();

            // Request recommendation for current form state:
            // - category_id (can be null)
            // - logged-in user
            // - selected month & year
            $recommendation = $aiService->getBudgetRecommendation(
                $this->category_id,
                Auth::user()->id,
                $this->month,
                $this->year
            );

            // If AI returns a valid recommendation
            if ($recommendation) {
                // Store it in a Livewire property to be used in the view
                $this->aiRecommendation = $recommendation;
                // Show the AI recommendation panel/modal in the UI
                $this->showAIRecommendation = true;
            } else {
                // If no recommendation is returned, show a friendly error
                session()->flash('ai-error', 'Unable to generate recommendation. Please try again.');
            }
        } catch (\Exception $e) {
            // If something goes wrong (AI error, network, etc.), show fallback message
            session()->flash('ai-error', 'Ai service temporarily unavailable. Please try again later.');
        }
        // Hide loading spinner once everything is done
        $this->loadingRecommendation = false;
    }

    // When user clicks "Apply Recommended / Min / Max" in the UI
    public function applyRecommendation($type = 'recommended')
    {
        // Ensure we actually have an AI recommendation
        if ($this->aiRecommendation) {
            // Set the form's amount value to the chosen recommendation type
            // If type key doesn't exist, fall back to 'recommended'
            $this->amount = $this->aiRecommendation[$type] ?? $this->aiRecommendation['recommended'];
        }
    }
    // Close/hide the AI recommendation panel/modal
    public function closeAIRecommendation()
    {
        $this->showAIRecommendation = false;
    }


    public function render()
    {
        return view('livewire.budget-form', [
            'categories' => $this->categories,
            'months' => $this->months,
            'years' => $this->years
        ]);
    }
}
