<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Goal;
use App\Models\IncomeSource;
use App\Models\Summary;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class FinancialContextBuilder
{
    /**
     * Assembles the static system prompt for StewardAI.
     * Includes role description, income sources, budget ratios,
     * categories, and hard-coded behavioral rules.
     */
    public function buildSystemPrompt(): string
    {
        $sections = [];

        // Role section
        $sections[] = $this->buildRoleSection();

        // Income section
        $sections[] = $this->buildIncomeSection();

        // Budget ratios section
        $sections[] = $this->buildBudgetRatiosSection();

        // Categories section
        $sections[] = $this->buildCategoriesSection();

        // Goals section
        $sections[] = $this->buildGoalsSection();

        // Behavioral rules section
        $sections[] = $this->buildBehavioralRulesSection();

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Assembles context that changes with each request:
     * account balances, recent transactions, budget vs actual, flagged transactions.
     */
    public function buildDynamicContext(): string
    {
        $sections = [];

        // Account balances
        $sections[] = $this->buildAccountBalancesSection();

        // Recent transactions (last 30 days, max 50)
        $sections[] = $this->buildRecentTransactionsSection();

        // Budget vs actual (current month)
        $sections[] = $this->buildBudgetVsActualSection();

        // Income tracking (expected vs actual)
        $sections[] = $this->buildIncomeTrackingSection();

        // Bills tracking
        $sections[] = $this->buildBillsSection();

        // Flagged transactions
        $sections[] = $this->buildFlaggedTransactionsSection();

        // Prior summaries
        $sections[] = $this->buildPriorSummaries();

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Special system prompt for the categorization job.
     * Lists all categories and instructs Ollama to respond in JSON.
     */
    public function buildCategorizationPrompt(): string
    {
        $lines = [];

        $lines[] = 'You are a financial transaction categorization assistant.';
        $lines[] = 'Your job is to categorize transactions into the correct budget category.';
        $lines[] = '';
        $lines[] = 'AVAILABLE CATEGORIES:';

        $categories = Category::orderBy('name')->get();
        foreach ($categories as $category) {
            $bucket = $category->default_bucket?->value ?? 'uncategorized';
            $essential = $category->is_essential ? 'essential' : 'non-essential';
            $lines[] = "  - {$category->name} (bucket: {$bucket}, {$essential})";
        }

        $lines[] = '';
        $lines[] = 'INSTRUCTIONS:';
        $lines[] = '  - You MUST respond in JSON format only.';
        $lines[] = '  - Use the exact category name from the list above for category_name.';
        $lines[] = '  - Set confidence as a decimal between 0 and 1 (e.g., 0.95 for high confidence).';
        $lines[] = '  - Set budget_bucket to the bucket value for your chosen category.';
        $lines[] = '  - If you are unsure, use a lower confidence score (below 0.7).';
        $lines[] = '';
        $lines[] = 'REQUIRED JSON RESPONSE FORMAT:';
        $lines[] = '{';
        $lines[] = '  "category_name": "Exact Category Name",';
        $lines[] = '  "confidence": 0.95,';
        $lines[] = '  "budget_bucket": "needs"';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    // --- Private helpers ---

    private function buildRoleSection(): string
    {
        return <<<'TEXT'
ROLE:
You are StewardAI, a personal financial advisor for a Christian household. You advise a married couple with 2 children. The family owns a home and has ongoing needs including vehicle maintenance and home renovations. Your advice is grounded in biblical stewardship principles — managing money as a resource entrusted by God, not merely owned by the individual.
TEXT;
    }

    private function buildIncomeSection(): string
    {
        $sources = IncomeSource::where('is_active', true)->orderBy('name')->get();

        if ($sources->isEmpty()) {
            return "INCOME SOURCES:\n  (none configured)";
        }

        $lines = ['INCOME SOURCES:'];
        $totalMonthly = 0;

        foreach ($sources as $source) {
            $monthly = $source->monthlyAmount();
            $totalMonthly += $monthly;
            $nextPay = $source->next_pay_date
                ? $source->next_pay_date->format('Y-m-d')
                : 'N/A';

            $lines[] = "  - {$source->name}: \${$this->fmt($source->amount)} ({$source->frequency})"
                . " | Monthly equiv: \${$this->fmt($monthly)}"
                . " | Next pay: {$nextPay}";
        }

        $lines[] = "  TOTAL MONTHLY INCOME: \${$this->fmt($totalMonthly)}";

        return implode("\n", $lines);
    }

    private function buildBudgetRatiosSection(): string
    {
        $ratios = AppSetting::getValue('budget_ratios', [
            'needs' => 50,
            'wants' => 30,
            'savings' => 20,
        ]);

        $needs = $ratios['needs'] ?? 50;
        $wants = $ratios['wants'] ?? 30;
        $savings = $ratios['savings'] ?? 20;

        return <<<TEXT
BUDGET RATIOS (target allocation):
  - Needs (essentials): {$needs}%
  - Wants (discretionary): {$wants}%
  - Savings / Giving: {$savings}%
TEXT;
    }

    private function buildCategoriesSection(): string
    {
        $categories = Category::orderBy('name')->get();

        if ($categories->isEmpty()) {
            return "CATEGORIES:\n  (none configured)";
        }

        $lines = ['CATEGORIES:'];

        foreach ($categories as $category) {
            $bucket = $category->default_bucket?->value ?? 'uncategorized';
            $essential = $category->is_essential ? 'essential' : 'non-essential';
            $lines[] = "  - {$category->name} | bucket: {$bucket} | {$essential}";
        }

        return implode("\n", $lines);
    }

    private function buildBehavioralRulesSection(): string
    {
        return <<<'TEXT'
BEHAVIORAL RULES:
  1. Be critical and direct when discretionary (wants) spending exceeds the budget. Do not soften the message.
  2. Accept increases in essential spending as sometimes necessary, but flag consistent overages.
  3. Always maintain a biblical stewardship perspective — money is a tool for provision, generosity, and purpose, not an end in itself.
  4. Connect every major financial decision to its impact on the family's savings goals and giving capacity.
  5. Maximize enjoyment within budget boundaries — do not make frugality the goal; wise stewardship is the goal.
  6. When advising on future purchases, provide specific target dates based on current savings rates.
  7. Reference the 50/30/20 (needs/wants/savings) framework when evaluating spending patterns.
TEXT;
    }

    private function buildAccountBalancesSection(): string
    {
        $accounts = Account::orderBy('name')->get();

        if ($accounts->isEmpty()) {
            return "ACCOUNT BALANCES:\n  (no accounts)";
        }

        $lines = ['ACCOUNT BALANCES:'];

        foreach ($accounts as $account) {
            $type = $account->type?->value ?? 'account';
            $current = $this->fmt($account->current_balance);
            $available = $this->fmt($account->available_balance);
            $lines[] = "  - {$account->name} ({$type}): current \${$current} | available \${$available}";
        }

        return implode("\n", $lines);
    }

    private function buildRecentTransactionsSection(): string
    {
        $since = now()->subDays(30)->toDateString();

        $transactions = Transaction::with('category')
            ->where('date', '>=', $since)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();

        if ($transactions->isEmpty()) {
            return "RECENT TRANSACTIONS (last 30 days):\n  (none)";
        }

        $lines = ['RECENT TRANSACTIONS (last 30 days):'];

        foreach ($transactions as $txn) {
            $date = $txn->date->format('Y-m-d');
            $merchant = $txn->merchant_name ?? $txn->description ?? 'Unknown';
            $sign = $txn->amount >= 0 ? '+' : '-';
            $amount = $this->fmt(abs($txn->amount));
            $category = $txn->category?->name ?? 'Uncategorized';
            $lines[] = "  - {$date} | {$merchant} | {$sign}\${$amount} | {$category}";
        }

        return implode("\n", $lines);
    }

    private function buildBudgetVsActualSection(): string
    {
        $currentMonth = now()->format('Y-m');

        $budgets = Budget::with('category')
            ->orderBy('budgeted_amount', 'desc')
            ->get();

        if ($budgets->isEmpty()) {
            return "BUDGET VS ACTUAL (current month {$currentMonth}):\n  (no budgets set)";
        }

        $lines = ["BUDGET VS ACTUAL (current month {$currentMonth}):"];

        foreach ($budgets as $budget) {
            $categoryName = $budget->category?->name ?? 'Unknown';

            // Sum actual spending for this category in current month
            $actual = abs(Transaction::where('category_id', $budget->category_id)
                ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->where('amount', '<', 0)
                ->sum('amount'));

            $budgeted = (float) $budget->budgeted_amount;
            $actualFloat = (float) $actual;
            $remaining = $budgeted - $actualFloat;
            $status = $remaining >= 0 ? 'under' : 'over';

            $lines[] = "  - {$categoryName}: budgeted \${$this->fmt($budgeted)}"
                . " | actual \${$this->fmt($actualFloat)}"
                . " | remaining \${$this->fmt(abs($remaining))} ({$status})";
        }

        return implode("\n", $lines);
    }

    private function buildIncomeTrackingSection(): string
    {
        $currentMonth = now()->format('Y-m');

        $expectedIncome = IncomeSource::where('is_active', true)->get()
            ->sum(fn ($s) => $s->monthlyAmount());

        $actualIncome = (float) Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->where('budget_bucket', 'income')
            ->where('amount', '>', 0)
            ->sum('amount');

        $savingsDeposits = (float) abs(Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->where('budget_bucket', 'savings')
            ->where('amount', '<', 0)
            ->sum('amount'));

        $savingsWithdrawals = (float) Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->where('budget_bucket', 'transfer')
            ->where('amount', '>', 0)
            ->sum('amount');

        $netSavings = $savingsDeposits - $savingsWithdrawals;

        $variance = $actualIncome - $expectedIncome;
        $varianceLabel = $variance >= 0 ? 'ahead' : 'behind';

        $lines = ["INCOME TRACKING (current month {$currentMonth}):"];
        $lines[] = "  Expected income: \${$this->fmt($expectedIncome)}";
        $lines[] = "  Actual income: \${$this->fmt($actualIncome)} (\${$this->fmt(abs($variance))} {$varianceLabel})";
        $lines[] = "  Savings deposits: \${$this->fmt($savingsDeposits)}";
        $lines[] = "  Savings withdrawals: \${$this->fmt($savingsWithdrawals)}";
        $lines[] = "  Net savings: \${$this->fmt($netSavings)}";

        return implode("\n", $lines);
    }

    private function buildFlaggedTransactionsSection(): string
    {
        $flagged = Transaction::with('category')
            ->where('needs_review', true)
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        if ($flagged->isEmpty()) {
            return "FLAGGED TRANSACTIONS (needs review):\n  (none)";
        }

        $lines = ['FLAGGED TRANSACTIONS (needs review):'];

        foreach ($flagged as $txn) {
            $date = $txn->date->format('Y-m-d');
            $merchant = $txn->merchant_name ?? $txn->description ?? 'Unknown';
            $sign = $txn->amount >= 0 ? '+' : '-';
            $amount = $this->fmt(abs($txn->amount));
            $category = $txn->category?->name ?? 'Uncategorized';
            $lines[] = "  - {$date} | {$merchant} | {$sign}\${$amount} | {$category}";
        }

        return implode("\n", $lines);
    }

    private function buildGoalsSection(): string
    {
        $goals = Goal::where('is_completed', false)
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END")
            ->get();

        if ($goals->isEmpty()) {
            return "FINANCIAL GOALS: None set yet.";
        }

        $lines = ['FINANCIAL GOALS:'];

        foreach ($goals as $goal) {
            $progress = $goal->progressPercent();
            $remaining = $this->fmt($goal->remaining());
            $target = $this->fmt((float) $goal->target_amount);
            $current = $this->fmt((float) $goal->current_amount);

            $line = "- {$goal->name} [{$goal->priority}]: \${$current} / \${$target} ({$progress}% done, \${$remaining} remaining";

            if ($goal->target_date) {
                $byDate = $goal->target_date->format('M Y');
                $monthly = $goal->monthlySavingsNeeded();
                $monthlyStr = $monthly !== null ? $this->fmt($monthly) : '0.00';
                $line .= " by {$byDate} (need \${$monthlyStr}/mo)";
            }

            $line .= ')';
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function buildBillsSection(): string
    {
        $bills = Bill::where('is_active', true)->orderBy('due_day')->get();

        if ($bills->isEmpty()) {
            return "BILLS (current month):\n  (no bills tracked)";
        }

        $now = now();
        $periodStart = $now->copy()->startOfMonth();
        $periodEnd = $now->copy()->endOfMonth();

        $lines = ['BILLS (current month):'];
        $totalUnpaid = 0;

        foreach ($bills as $bill) {
            $status = $bill->statusForMonth($now);
            $payment = $bill->matchingTransaction($periodStart, $periodEnd);

            $amountStr = $bill->is_fixed
                ? '$' . number_format((float) $bill->amount, 2)
                : '~$' . number_format((float) $bill->amount, 2);

            $dueDate = $bill->dueDateForMonth($now);
            $statusLabel = match($status) {
                'paid' => 'PAID on ' . $payment->date->format('M j') . ' ($' . number_format(abs((float) $payment->amount), 2) . ')',
                'overdue' => 'OVERDUE (was due ' . $dueDate->format('M j') . ')',
                'due_soon' => 'DUE SOON (' . $dueDate->format('M j') . ', ' . now()->diffInDays($dueDate) . ' days)',
                'upcoming' => 'due ' . $dueDate->format('M j'),
            };

            if ($status !== 'paid' && $bill->amount) {
                $totalUnpaid += (float) $bill->amount;
            }

            $lines[] = "  - {$bill->name}: {$amountStr}, {$statusLabel}" . ($bill->is_autopay ? ' [autopay]' : '');
        }

        $lines[] = "  Total upcoming unpaid: \$" . number_format($totalUnpaid, 2);

        return implode("\n", $lines);
    }

    private function buildPriorSummaries(): string
    {
        $summaries = Summary::where('type', 'daily')
            ->orderByDesc('period_start')
            ->limit(3)
            ->get();

        if ($summaries->isEmpty()) {
            return '';
        }

        $lines = ['RECENT SUMMARIES:'];

        foreach ($summaries as $summary) {
            $date = $summary->period_start->format('M j');
            $spent = $this->fmt((float) $summary->total_spent);
            $analysis = $summary->ai_analysis ?? '';

            $line = "- {$date}: \${$spent} spent";
            if ($analysis) {
                $line .= " — {$analysis}";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Format a number as a dollar amount string with 2 decimal places.
     */
    private function fmt(float|int|string|null $value): string
    {
        return number_format((float) $value, 2);
    }
}
