<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Expense;
use App\Models\Category;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class BudgetAIService
{
    /**
     * Main function:
     * Gets an AI-generated budget recommendation for a user.
     */
    public function getBudgetRecommendation(?int $categoryId, int $userId, int $month, int $year): ?array
    {
        try {
            // Step 1: Get historical spending data (last 3 months before selected month)
            $historicalData = $this->getHistoricalSpending($categoryId,  $userId,  $month,  $year);

            // If there is no data, AI cannot generate recommendations
            if (empty($historicalData)) {
                return null;
            }

            // Step 2: Build the AI prompt text using historical data
            $prompt = $this->createPrompt(
                $historicalData,
                $categoryId,
                $month,
                $year
            );

            // Step 3: Send the prompt to Gemini AI to get the recommendation
            $response =  Gemini::generativeModel(model: 'gemini-2.0-flash')->generateContent($prompt);

            // Step 4: Interpret AI response and convert to usable array
            return $this->parseAIResponse($response->text(), $historicalData);
        } catch (\Exception $e) {
            // Log error if AI or DB fails
            Log::error('Budget AI Recommendation Error:' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch and process historical spending for last 3 months.
     * Returns totals, averages, min, max, trend, and titles of expenses.
     */
    private function getHistoricalSpending(?int $categoryId, int $userId, int $targetMonth, int $targetYear)
    {
        $expenses = []; // Holds monthly expense details
        $monthlyTotals = [];    // Holds only the total amounts for each month

        // Loop through previous 3 months (not including target month)
        for ($i = 1; $i <= 3; $i++) {
            // Calculate the month we want (e.g., if target is Oct, get Sep, Aug, Jul)
            $date = Carbon::create($targetYear, $targetMonth, 1)->subMonths($i);

            // Base query for this month and user
            $query = Expense::where('user_id', $userId)
                ->whereMonth('date', $date->month)
                ->whereYear('date', $date->year);

            // If category is selected, filter by category
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            // Fetch all expenses for that month
            $monthExpenses = $query->get();
            // Get the total amount spent this month
            $total = $monthExpenses->sum('amount');

            // If user spent something in this month, store details
            if ($total > 0) {
                $expenses[] = [
                    'month' => $date->format('F Y'),
                    'total' => $total,
                    'count' => $monthExpenses->count(),
                    'expenses' => $monthExpenses->pluck('title')->take(10)->toArray(), // Top 10 names
                ];
                $monthlyTotals[] = $total;
            }

            // Return summary of collected data
            return [
                'expenses' => $expenses, // list of last-month expenses
                'average' => !empty($monthlyTotals) ? array_sum($monthlyTotals) / count($monthlyTotals) : 0,
                'min' => !empty($monthlyTotals) ? min($monthlyTotals) : 0,
                'max' => !empty($monthlyTotals) ? max($monthlyTotals) : 0,
                'trend' => $this->calculateTrend($monthlyTotals), // increasing / decreasing / stable
            ];
        }
    }

    /**
     * Detect whether spending is increasing, decreasing, or stable.
     */
    private function calculateTrend($monthlyTotals)
    {
        // If only 1 month data → cannot detect trend
        if (count($monthlyTotals) < 2) {
            return 'stable';
        }

        // Recent = month 1, Oldest = last month
        $recent = $monthlyTotals[0];
        $oldest = $monthlyTotals[count($monthlyTotals) - 1];

        // Percentage change = (difference / original) * 100
        $percentageChange = (($recent - $oldest) / $oldest) * 100;

        // Trend detection rules
        if ($percentageChange > 10) {
            return 'increasing';
        } elseif ($percentageChange < -10) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Create the prompt that will be sent to Gemini AI.
     * Includes monthly spending, statistics, trend, and instructions.
     */
    private function createPrompt(array $historicalData, ?int $categoryId, int $month, int $year)
    {
        // Get category name or fallback
        $categoryName = $categoryId ? Category::find($categoryId)?->name ?? 'this category' : 'overall spending';

        // Format target month for AI
        $targetMonth = Carbon::create($year, $month, 1)->format('F Y');

        // Basic role prompt
        $prompt = "You are a personal finance advisor. Analyze the following spending data and provide a budget recommendation.\n\n";

        // Add category and month info
        $prompt .= "Category: {$categoryName}\n";
        $prompt .= "Target Month: {$targetMonth}";
        $prompt .= "Historical Spending (Last 3 Months): \n";

        // Add per-month breakdown
        foreach ($historicalData['expenses'] as $expense) {
            $prompt .= "- {$expense['month']}: \${expense['total']} ({$expense['count']}) expenses)\n";

            // Add top 5 items
            if (!empty($expense['expenses'])) {
                $prompt .= " Top items: " . implode(', ', array_slice($expense['expenses'], 0, 5));
            }
        }

        // Add statistical summary
        $prompt .= "\nSpending Statistics:\n";
        $prompt .= "- Average: \$" . number_format($historicalData['average'], 2) . "\n";
        $prompt .= "- Minimum: \$" . number_format($historicalData['min'], 2) . "\n";
        $prompt .= "- Maximum: \$" . number_format($historicalData['max'], 2) . "\n";
        $prompt .= "- Trend: {$historicalData['trend']}\n\n";

        // Ask AI to generate:
        $prompt .= "Based on this data, provide:\n";
        $prompt .= "1. A recommended budget amount (single number)\n";
        $prompt .= "2. A minimum safe amount\n";
        $prompt .= "3. A maximum comfortable amount\n";
        $prompt .= "4. A brief explanation (2-3 sentences) why you recommend this amount\n";
        $prompt .= "5. One actionable tip to stay within budget\n\n";

        // Required JSON format
        $prompt .= "Format your response as JSON with these exact keys:\n";
        $prompt .= '{"recommended": 500, "min": 450, "max": 550, "explanation": "...", "tip": "..."}';

        return $prompt;
    }

    /**
     * Extract JSON from AI response text and convert to array.
     * If AI response fails or is invalid → fallback recommendation.
     */
    private function parseAIResponse(string $response, array $historicalData)
    {
        try {
            // Try to extract JSON from AI response using regex pattern
            if (preg_match('/\{[^}]+\}/', $response, $matches)) {
                // Convert extracted JSON string into array
                $json = json_decode($matches[0], true);

                // If valid JSON and mandatory "recommended" exists
                if ($json && isset($json['recommended'])) {
                    return [
                        'recommended' => (float) $json['recommended'],
                        'min' => (float) ($json['min'] ?? $json['recommended'] * 0.9),
                        'max' => (float) ($json['max'] ?? $json['recommended'] * 1.1),
                        'explanation' => $json['explanation'] ?? 'Based on your spending patterns.',
                        'tip' => $json['tip'] ?? 'Track your expenses regularly to stay on budget.',
                        'confidence' => $this->calculateConfidence($historicalData),
                    ];
                }
            }

            // If JSON parsing fails → fallback logic
            return $this->getFallbackRecommendation($historicalData);
        } catch (\Exception $e) {
            Log::error('Failed to Parse AI response' . $e->getMessage());
            return $this->getFallbackRecommendation($historicalData);
        }
    }

    /**
     * If AI fails, fallback logic based on averages.
     */
    private function getFallbackRecommendation(array $historicalData)
    {
        $average = $historicalData['average'];

        // Recommended = average + 10% buffer
        $recommended = round($average * 1.1, 2);

        return [
            'recommended' => $recommended,
            'min' => round($average * 0.95, 2), // average - 5%
            'max' => round($average * 1.2, 2), // average + 20%
            'explanation' => "Based on your average spending of $" . number_format($average, 2) . " over the last 3 months, with a 10% buffer for unexpected expenses.",
            'tip' => "Review your expenses weekly to catch any overspending early.",
            'confidence' => $this->calculateConfidence($historicalData),
        ];
    }

    /**
     * Confidence level: based on how many months of data exist.
     */
    private function calculateConfidence(array $historicalData)
    {
        $monthsWithData = count($historicalData['expenses']);

        if ($monthsWithData >= 3) {
            return 'high';
        } elseif ($monthsWithData === 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Checks if user has enough expenses in the last 3 months
     * to generate a meaningful AI recommendation.
     */
    public function hasEnoughHistoricalData(?int $categoryId, int $userId)
    {
        // Get the date 3 months ago from today
        $threeMonthsAgo = now()->subMonths(3)->startOfDay();

        // Fetch expenses since last 3 months
        $query = Expense::where('user_id', $userId)
            ->where('date', '>=', $threeMonthsAgo);

        // Apply category filter if needed
        if ($categoryId) {
            $query = $query->where('category_id', $categoryId);
        }
        // At least 6 expenses required (to avoid weak data)
        return $query->count() > 5;
    }
}
