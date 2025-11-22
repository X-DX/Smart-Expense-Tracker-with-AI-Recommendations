<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Expense;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title(content: "Expense Form - ExpenseApp")]

class ExpenseForm extends Component
{
    /*
    |--------------------------------------------------------------------------
    | PUBLIC PROPERTIES
    |--------------------------------------------------------------------------
    |
    | These variables are automatically available to your Livewire view.
    | They are bound to form inputs using wire:model, meaning whenever the
    | user types something, these variables update in real-time.
    */
    public $expenseId; // Holds the ID of the expense when editing
    public $amount = ''; // Amount field (input number)
    public $title = ''; // Title of expense
    public $description = ''; // Description of expense (optional)
    public $date; // Date of expense
    public $category_id = ''; // ID of category (dropdown)
    public $type = 'one-time'; // Default expense type is one-time
    public $recurring_frequency = 'monthly'; // Default frequency if expense is recurring
    public $recurring_start_date; // Start date of recurring expense
    public $recurring_end_date; // End date of recurring expense

    public $isEdit = false; // Boolean to detect if we are editing or creating

    /*
    |--------------------------------------------------------------------------
    | VALIDATION RULES
    |--------------------------------------------------------------------------
    |
    | The rules() method defines all validation constraints for form fields.
    | Livewire automatically calls these when you run $this->validate().
    |
    | Validation helps ensure that only clean and valid data enters the database.
    */
    protected function rules()
    {
        // Default validation rules for both one-time and recurring expenses
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:one-time,recurring',
        ];

        // If user selected "recurring", add more rules specific to recurring payments
        if ($this->type === 'recurring') {
            $rules['recurring_frequency'] = 'required|in:daily,weekly,monthly,yearly'; // Must choose one of these
            $rules['recurring_start_date'] = 'required|date'; // Start date must be valid
            $rules['recurring_end_date'] = 'nullable|date|after:recurring_start_date'; // End date optional but must come after start
        }

        return $rules;
    }

    /*
    |--------------------------------------------------------------------------
    | MOUNT METHOD
    |--------------------------------------------------------------------------
    |
    | Livewire automatically calls mount() when the component is initialized.
    | This is similar to a constructor. It decides if we are creating or editing.
    | If an $expenseId is passed, it means user is editing an existing record.
    */
    public function mount($expenseId = null)
    {
        if ($expenseId) {
            // Editing mode
            $this->isEdit = true;
            $this->expenseId = $expenseId;

            // Load expense data from database into form fields
            $this->loadExpense();
        } else {
            // Creating mode
            // Set default date values to today's date
            $this->date = now()->format('Y-m-d');
            $this->recurring_start_date = now()->format('Y-m-d');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD EXPENSE DATA (EDIT MODE)
    |--------------------------------------------------------------------------
    |
    | This function fetches an expense record from the database
    | based on the provided $expenseId, checks if the user owns it,
    | and fills all form fields with the data so user can edit.
    */
    public function loadExpense()
    {
        // Try to find the expense record by ID.
        // If not found, it automatically throws a 404 error.
        $expense = Expense::findOrFail($this->expenseId);

        // Security check: ensure the logged-in user owns this expense.
        if ($expense->user_id !== Auth::id()) {
            abort(403);
        }

        // Fill component properties with existing data (so form fields are pre-filled)
        $this->amount = $expense->amount;
        $this->title = $expense->title;
        $this->description = $expense->description;
        $this->date = $expense->date->format('Y-m-d');
        $this->category_id = $expense->category_id;
        $this->type = $expense->type;
        $this->recurring_frequency = $expense->recurring_frequency;
        $this->recurring_start_date = $expense->recurring_start_date->format('Y-m-d');
        $this->recurring_end_date = $expense->recurring_end_date;
    }

    /*
    |--------------------------------------------------------------------------
    | COMPUTED PROPERTY: CATEGORIES
    |--------------------------------------------------------------------------
    |
    | This function automatically returns the list of categories that belong
    | to the currently logged-in user. It can be used directly in Blade view.
    | Livewire will call this function when rendering the component.
    */
    #[Computed]
    public function categories()
    {
        // Fetch categories that belong only to current user
        // Sort them alphabetically by name
        return Category::where('user_id', Auth::id())
            ->orderBy('name', 'asc')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE FUNCTION
    |--------------------------------------------------------------------------
    |
    | This is the core function that handles both creating and updating records.
    | 1. It validates all inputs.
    | 2. Prepares the data array for saving.
    | 3. Checks if it’s an edit or create action.
    | 4. Performs the respective database operation.
    | 5. Sets a flash message and redirects user back to expenses list.
    */
    public function save()
    {
        // Step 1: Validate the form fields using the rules() method above
        $this->validate();

        // Step 2: Prepare clean data for saving to the database
        $data = [
            'user_id' => Auth::id(), // Always save the ID of the logged-in user
            'amount' => $this->amount,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date,
            'category_id' => $this->category_id ?: null, // Use null if no category selected
            'type' => $this->type,
        ];

        // Step 3: Add recurring fields if expense is recurring
        if ($this->type === 'recurring') {
            $data['recurring_frequency'] = $this->recurring_frequency;
            $data['recurring_start_date'] = $this->recurring_start_date;
            $data['recurring_end_date'] = $this->recurring_end_date ?: null;
        } else {
            // Otherwise, make sure recurring fields are null for one-time expenses
            $data['recurring_frequency'] = null;
            $data['recurring_start_date'] = null;
            $data['recurring_end_date'] = null;
        }

        // Step 4: Save or Update record depending on mode
        if ($this->isEdit) {
            // Editing existing expense
            $expense = Expense::findOrFail($this->expenseId);

            // Security: ensure the user owns this expense
            if ($expense->user_id !== Auth::user()->id) {
                abort(403);
            }

            // Update the record with new data
            $expense->update($data);
            session()->flash('message', 'Expense updated successfully.');
        } else {
            // Creating new expense
            Expense::create($data);

            // Show success message for create
            session()->flash('message', 'Expense created successfully.');
        }

        // Step 5: Redirect user back to expense list page
        return redirect()->route('expenses.index');
    }


    /*
    |--------------------------------------------------------------------------
    | RENDER METHOD
    |--------------------------------------------------------------------------
    |
    | The render() function returns the Livewire view that should be displayed.
    | It passes any necessary data (like categories) to that Blade view.
    |
    | This view file is usually located at:
    | resources/views/livewire/expense-form.blade.php
    */
    public function render()
    {
        return view('livewire.expense-form', [
            'categories' => $this->categories,
        ]);
    }
}
