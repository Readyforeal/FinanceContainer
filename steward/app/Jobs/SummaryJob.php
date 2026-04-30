<?php

namespace App\Jobs;

use App\Mail\DailySummaryMail;
use App\Models\AppSetting;
use App\Models\IncomeSource;
use App\Models\Summary;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public readonly string $type = 'daily',
    ) {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): void
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod();

        $transactions = Transaction::whereBetween('date', [
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
        ])->get();

        $needsSpent = $transactions
            ->filter(fn ($t) => $this->bucketValue($t) === 'needs')
            ->sum(fn ($t) => (float) $t->amount);

        $wantsSpent = $transactions
            ->filter(fn ($t) => $this->bucketValue($t) === 'wants')
            ->sum(fn ($t) => (float) $t->amount);

        $savingsSpent = $transactions
            ->filter(fn ($t) => $this->bucketValue($t) === 'savings')
            ->sum(fn ($t) => (float) $t->amount);

        $totalSpent = $needsSpent + $wantsSpent + $savingsSpent;

        $totalIncome = IncomeSource::where('is_active', true)
            ->get()
            ->sum(fn ($source) => $source->monthlyAmount());

        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<TEXT
Please generate a concise financial analysis for this {$this->type} summary period ({$periodStart->toDateString()} to {$periodEnd->toDateString()}).

Period totals:
- Total Spent: \${$totalSpent}
- Needs: \${$needsSpent}
- Wants: \${$wantsSpent}
- Savings: \${$savingsSpent}
- Monthly Income: \${$totalIncome}

CURRENT FINANCIAL CONTEXT:
{$dynamicContext}
TEXT;

        $aiAnalysis = $ollama->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ]);

        $summary = Summary::updateOrCreate(
            [
                'type' => $this->type,
                'period_start' => $periodStart->toDateString(),
            ],
            [
                'period_end' => $periodEnd->toDateString(),
                'total_income' => round($totalIncome, 2),
                'total_spent' => round($totalSpent, 2),
                'needs_spent' => round($needsSpent, 2),
                'wants_spent' => round($wantsSpent, 2),
                'savings_spent' => round($savingsSpent, 2),
                'ai_analysis' => $aiAnalysis,
            ]
        );

        $recipients = AppSetting::getValue('email_recipients', []);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new DailySummaryMail($summary));
        }
    }

    private function resolvePeriod(): array
    {
        return match ($this->type) {
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function bucketValue(Transaction $transaction): ?string
    {
        if ($transaction->budget_bucket === null) {
            return null;
        }

        // budget_bucket is cast to BudgetBucket enum; get string value
        if ($transaction->budget_bucket instanceof \App\Enums\BudgetBucket) {
            return $transaction->budget_bucket->value;
        }

        return (string) $transaction->budget_bucket;
    }
}
