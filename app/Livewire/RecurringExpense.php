<?php

namespace App\Livewire;

use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title(content: "Recurring Expenses - ExpenseApp")]

class RecurringExpense extends Component
{
    // Controls whether the delete confirmation modal is visible
    public $showDetailModel = false;

    // Stores the ID of the expense the user chose to delete
    public $expenseToDelete = null;

    /**
     * Called when user clicks "Delete" on a recurring expense.
     * This method *does not delete*, it only opens the confirmation modal.
     */
    public function confirmDelete($expenseId)
    {
        $this->expenseToDelete = $expenseId; // Store the ID of the selected expense
        $this->showDetailModel = true; // Show the delete confirmation modal
    }

    /**
     * Actually deletes the recurring expense and all its child expenses.
     * Runs only after user confirms deletion in the modal.
     */
    public function deleteExpense()
    {
        // Only run if an expense ID exists
        if ($this->expenseToDelete) {
            // Fetch the parent recurring expense or fail with 404
            $expense = Expense::findorFail($this->expenseToDelete);

            // Security check: ensure logged-in user owns this expense
            if ($expense->user_id !== Auth::id()) {
                abort(403);
            }

            // delete the dependent expense
            // Delete all generated child expenses (instances created automatically)
            $expense->childExpenses()->delete();

            // Delete the recurring parent expense itself
            $expense->delete();

            // Flash success message to session for UI feedback
            session()->flash('message', 'Recurring Expense deleted successfully!');

            // Close modal and reset temporary delete variable
            $this->showDetailModel = false;
            $this->expenseToDelete = null;
        }
    }

    /**
     * Computed property that returns all recurring expenses for the logged-in user.
     * Uses model scopes like `forUser()` and `recurring()` for cleaner queries.
     */
    #[Computed]
    public function recurringExpenses()
    {
        return Expense::with(['category', 'childExpenses']) // Eager-load category + children
            ->forUser(Auth::id()) // Custom scope: filter by user
            ->recurring() // Custom scope: only recurring expenses
            ->get(); // Execute and return collection
    }

    /**
     * Computed property that fetches all categories owned by current user.
     * Used typically for dropdown filtering inside the view.
     */
    #[Computed]
    public function categories()
    {
        return Category::where('user_id', Auth::id()) // Only logged-in user's categories
            ->orderBy('name') // Sort alphabetically
            ->get(); // Fetch results
    }


    /**
     * Render method returns the Livewire Blade view.
     * Automatically runs when data updates, re-rendering the UI.
     */
    public function render()
    {
        return view('livewire.recurring-expense', [
            'recurringExpenses' => $this->recurringExpenses(), // Pass recurring expenses
            'categories' => $this->categories() // Pass categories
        ]);
    }
}
