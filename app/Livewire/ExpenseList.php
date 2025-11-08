<?php

namespace App\Livewire;

use App\Models\Expense;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseList extends Component
{
    use WithPagination;

    // =========================================================================
    // PUBLIC PROPERTIES
    // =========================================================================
    // These act like “public switches” that sync with the UI (Livewire magic).

    public $search = ""; // Stores the search keyword typed by the user
    public $selectedCategory = ""; // Stores the selected category filter
    public $startDate = ""; // Start date for date-range filter
    public $endDate = ""; // End date for date-range filter
    public $sortBy = "date"; // Field used for sorting results
    public $sortDirection = "desc"; // Sorting direction: 'asc' or 'desc'
    public $showFilters = false; // Controls visibility of filter section in UI

    // =========================================================================
    // mount(): Runs the moment this component is created.
    // Purpose: Set default start/end dates if the user hasn’t selected any.
    // =========================================================================
    public function mount(): void
    {
        // If the user has not selected a start date,
        // set the start of the current month. (Cleaner UX)
        if (empty($this->startDate)) {
            $this->startDate = now()->startOfMonth()->format("Y-m-d");
        }
        // Same for end date. Default = end of current month.
        if (empty($this->endDate)) {
            $this->endDate = now()->endOfMonth()->format("Y-m-d");
        }
    }

    // =========================================================================
    // sortBy(): Handles sorting when the user clicks table headers.
    // Logic:
    // - If user clicks same column, toggle ASC/DESC.
    // - If user clicks new column, sort ASC by default.
    // =========================================================================
    public function sortBy($field)
    {
        // If user clicks the same column, toggle sort direction
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection == "asc" ? "desc" : "asc";
        } else {
            // If it's a new column, set sorting to ascending
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    // =========================================================================
    // deleteExpense(): Deletes expense after checking user ownership.
    // Logic:
    // - Find expense
    // - Stop unauthorized delete attempts
    // - Delete + show success message
    // =========================================================================
    public function deleteExpense($expenseId)
    {
        $expense = Expense::findOrFail($expenseId); // Fetch expense or fail

        // Only the owner should delete the expense
        if ($expense->user_id !== Auth::id()) {
            abort(403, 'Your not authorized to perform this function');
        }
        $expense->delete();
        session()->flash('message', 'Expense delete successfully');
    }

    // =========================================================================
    // expenses(): Computed property
    // Purpose: Build the list of expenses with all filters + sorting.
    //
    // This function builds a query step-by-step like:
    // 1. Start query for logged-in user
    // 2. Apply search filter
    // 3. Apply category filter
    // 4. Apply date filters
    // 5. Apply sorting
    // 6. Return paginated results
    // =========================================================================
    #[Computed]
    public function expenses()
    {
        // Create the base query for fetching expenses belonging to the logged-in user.
        // 'with(category)' loads the related category in a single query (Eager Loading).
        $query = Expense::with('category')->forUser(Auth::id());

        // Search by title or description
        if ($this->search) {
            $query->where(
                column: 'title',
                operator: 'like',
                value: '%' . $this->search . '%'
            )->orWhere(
                column: 'description',
                operator: 'like',
                value: '%' . $this->search . '%'
            );
        }

        // Filter by category
        if ($this->selectedCategory) {
            $query->where(column: 'category_id', operator: $this->selectedCategory);
        }

        // Filter by start date
        if ($this->startDate) {
            $query->where(column: 'date', operator: '>=', value: $this->startDate);
        }

        // Filter by end date
        if ($this->endDate) {
            $query->where(column: 'date', operator: '<=', value: $this->endDate);
        }

        // Apply sorting + pagination
        return $query
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    // =========================================================================
    // total(): Computed property
    // Purpose: Calculate total amount after applying the same filters.
    // =========================================================================
    #[Computed]
    public function total()
    {
        $query = Expense::forUser(Auth::id());

        // Apply same filters used in expenses()
        if ($this->search) {
            $query->where(
                column: 'title',
                operator: 'like',
                value: '%' . $this->search . '%'
            )->orWhere(
                column: 'description',
                operator: 'like',
                value: '%' . $this->search . '%'
            );
        }

        if ($this->selectedCategory) {
            $query->where(column: 'category_id', operator: $this->selectedCategory);
        }

        if ($this->startDate) {
            $query->where(column: 'date', operator: '>=', value: $this->startDate);
        }

        if ($this->endDate) {
            $query->where(column: 'date', operator: '<=', value: $this->endDate);
        }

        return $query->sum('amount');
    }

    // =========================================================================
    // categories(): Computed property
    // Purpose: Fetch categories for dropdown for logged-in user.
    // =========================================================================
    #[Computed]
    public function categories()
    {
        return Category::where(
            column: 'user_id',
            operator: Auth::id()
        )->orderBy('name')->get();
    }

    // =========================================================================
    // Livewire Lifecycle Hooks
    // Purpose: Reset to page 1 whenever a filter changes.
    // =========================================================================
    public function updatingSeach()
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatingStartDate()
    {
        $this->resetPage();
    }

    public function updatingEndDate()
    {
        $this->resetPage();
    }

    // =========================================================================
    // clearFilters(): Reset everything back to default
    // =========================================================================
    public function clearFilters()
    {
        $this->search = '';
        $this->selectedCategory = '';
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    // =========================================================================
    // render(): Send data to the Blade view.
    // Note: There is a typo in your original code: $this->categoties
    // =========================================================================
    public function render()
    {
        return view('livewire.expense-list', data: [
            'expenses' => $this->expenses,
            'total' => $this->total,
            'categories' => $this->categories,
        ]);
    }
}