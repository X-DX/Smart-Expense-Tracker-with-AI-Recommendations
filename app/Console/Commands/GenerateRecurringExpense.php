<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringExpense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * This is how you will run the command in terminal:
     * php artisan expenses:generate-recurring-expense
     */
    protected $signature = 'expenses:generate-recurring-expense';

    /**
     * The console command description.
     *
     * @var string
     * Shown in "php artisan list" under commands.
     */
    protected $description = 'Generate recurring expenses based on their schedule';

    /**
     * Execute the console command.
     * This is the main entry point of the command when executed.
     */
    public function handle()
    {
        // Display console message to show process has started.
        $this->info('Starting to generate recurring expenses...');

        // Fetch all recurring expenses that are not soft-deleted.
        $recurringExpenses = Expense::recurring()
            ->whereNull('deleted_at')
            ->get();

        $generatedCount = 0; // Track how many expenses were created.

        // Loop through each recurring expense to generate occurrences.
        foreach ($recurringExpenses as $expense) {
            $generated = $this->generateExpensesForRecurring($expense);
            $generatedCount += $generated; // Increase total count.
        }

        // Show summary message in terminal.
        $this->info("Successfully generated {$generatedCount} recurring expenses");

        // Log the summary into Laravel log file.
        Log::info("Generated {$generatedCount} recurring expenses", [
            'command' => 'expenses:generate-recurring',
            'timestamp' => now(),
        ]);

        // Return success exit code.
        return Command::SUCCESS;
    }

    /**
     * Generate missing occurrences for a single recurring expense.
     */
    private function generateExpensesForRecurring(Expense $recurringExpenses)
    {
        // Check if next occurrence should be generated.
        if (!$recurringExpenses->shouldGenerateNextOccurrence()) {
            return 0; // If no generation needed, return early.
        }

        // Get the next occurrence date based on frequency.
        $nextDate = $recurringExpenses->getNextOccurrenceDate();
        Log::info("next occurance {$nextDate}");
        $generatedCount = 0; // Track how many occurrences were created for this item.

        // Loop: generate all missing occurrence upto today
        while ($nextDate && $nextDate->lte(date: now())) {
            // Check if an occurrence already exists for this date.
            $exists = Expense::where('parent_expense_id', $recurringExpenses->id)
                ->whereDate('date', $nextDate)
                ->exists();

            // If not generated previously, create the expense occurrence.
            if (!$exists) {
                $this->createExpenseOccurrence($recurringExpenses, $nextDate);
                $generatedCount++;

                // Console output for each generated entry.
                $this->line("Generated: {$recurringExpenses->title} for {$nextDate->format('Y-m-d')}");
            }

            // Calculate the next scheduled occurrence.
            $nextDate = match ($recurringExpenses->recurring_frequency) {
                'daily' => $nextDate->copy()->addDay(),
                'weekly' => $nextDate->copy()->addweek(),
                'monthly' => $nextDate->copy()->addmonth(),
                'yearly' => $nextDate->copy()->addYear(),
                default => null,
            };

            // Stop if next date goes beyond defined end date.
            if ($recurringExpenses->recurring_end_date && $nextDate && $nextDate->gt($recurringExpenses->recurring_end_date)) {
                break;
            }

            // safety check : dont generate future expenses
            if ($nextDate && $nextDate->gt(now())) {
                break;
            }
        }
        return $generatedCount; // Return count for this recurring expense.
    }

    /**
     * Create a single generated occurrence for a recurring expense.
     */
    private function createExpenseOccurrence(Expense $recurringExpense, $date)
    {
        // Create an "auto-generated" one-time expense for the occurrence.
        Expense::create([
            'user_id' => $recurringExpense->user_id,
            'category_id' => $recurringExpense->category_id,
            'amount' => $recurringExpense->amount,
            'title' => $recurringExpense->title,
            'description' => $recurringExpense->description,
            'date' => $date,
            'type' => 'one-time',   // All generated records become one-time expenses.
            'parent_expense_id' => $recurringExpense->id, // Link to the main recurring expense.
            'is_auto_generated' => true,    // Mark as system-generated.
        ]);
    }
}
