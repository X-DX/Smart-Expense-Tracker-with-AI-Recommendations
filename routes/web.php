<?php

use App\Livewire\BudgetForm;
use App\Livewire\BudgetList;
use App\Livewire\Categories;
use App\Livewire\ExpenseForm;
use App\Livewire\ExpenseList;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // ---------- Categories ----------
    // Displays the "Categories" page component.
    // Uses a Livewire component called "Categories".
    // Named route: 'categories.index'.
    Route::get('categories', Categories::class)->name('categories.index');

    // ---------------------- Budgets -------------
    // This route defines a GET endpoint for displaying the list of budgets.
    // It maps the URL '/budgets' to the Livewire component 'BudgetList' 
    // and assigns a named route for easy reference within the application.
    Route::get('budgets', BudgetList::class)->name('budgets.index');

    // ----------------------
    // This route defines the page for creating a new budget entry.
    // It maps the '/budgets/create' URL to the Livewire component 'BudgetForm'
    // and assigns it a named route for easier reference in redirects or links.
    // ----------------------
    Route::get('budgets/create', BudgetForm::class)->name('budget.create');

    // ----------------------
    // This route defines the URL for editing an existing budget.
    // It uses a dynamic parameter {budgetId} to identify which budget record should be loaded,
    // and maps that request to the Livewire component 'BudgetForm'.
    // ----------------------
    Route::get('budgets/{budgetId}/edit', BudgetForm::class)->name('budgets.edit');

    // ---------------------- Expenses -------------
    // It does not take any dynamic parameters because it displays
    // all expenses* for the logged-in user, applying filters,
    // sorting, pagination, etc.
    // When a user visits this URL, Livewire automatically renders the 'ExpenseList' component
    Route::get('expenses', ExpenseList::class)->name('expenses.index');

    Route::get('/expenses/create', ExpenseForm::class)->name('expenses.create');

    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});