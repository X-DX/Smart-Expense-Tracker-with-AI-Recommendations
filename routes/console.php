<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Registering the scheduled task for generating recurring expenses
// We schedule this because recurring expenses must be created automatically
// without the user manually adding them every day/month/week.
// This ensures the system always generates the next occurrences on time.

// We call the console command 'expenses:generate-recurring-expense'
// so Laravel will execute our custom command automatically every day.
Schedule::command('expenses:generate-recurring-expense')
    // Run this command **every day at midnight (00:00)**  
    // because most billing/recurring systems generate occurrences daily.
    // ->dailyAt('00:00')
    ->everyFifteenSeconds()

    // Prevents multiple overlapping executions in case a previous run is still not finished  
    // (important for long loops or heavy load).
    ->withoutOverlapping()

    // Log a success message if the command runs correctly
    ->onSuccess(function () {
        Log::info('Reccurring expense generated successfully.');
    })

    // Log a failure message if the command fails
    ->onFailure(function () {
        Log::info('Failed to generate recurring expenses');
    });
