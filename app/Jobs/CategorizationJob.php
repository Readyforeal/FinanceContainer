<?php

namespace App\Jobs;

use App\Enums\BudgetBucket;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CategorizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(private array $transactionIds)
    {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $context): void
    {
        $threshold = AppSetting::getValue('categorization_confidence_threshold', 0.9);

        $categorizationPrompt = $context->buildCategorizationPrompt();

        $transactions = Transaction::whereIn('id', $this->transactionIds)
            ->where('needs_review', true)
            ->whereNull('category_id')
            ->get();

        foreach ($transactions as $transaction) {
            $userMessage = $this->buildUserMessage($transaction);

            $response = $ollama->chatJson($categorizationPrompt, [
                ['role' => 'user', 'content' => $userMessage],
            ]);

            $categoryName = $response['category_name'] ?? null;
            $confidence = isset($response['confidence']) ? (float) $response['confidence'] : 0.0;
            $budgetBucketValue = $response['budget_bucket'] ?? null;

            $category = $categoryName
                ? Category::where('name', $categoryName)->first()
                : null;

            $budgetBucket = $budgetBucketValue
                ? BudgetBucket::tryFrom($budgetBucketValue)
                : null;

            $transaction->update([
                'category_id' => $category?->id,
                'categorization_confidence' => $confidence,
                'needs_review' => $confidence < $threshold,
                'budget_bucket' => $budgetBucket,
            ]);
        }
    }

    private function buildUserMessage(Transaction $transaction): string
    {
        $merchant = $transaction->merchant_name ?? $transaction->description ?? 'Unknown';
        $amount = number_format((float) $transaction->amount, 2);
        $date = $transaction->date->format('Y-m-d');
        $hint = $transaction->plaid_category ?? 'N/A';

        return "Please categorize this transaction:\n"
            . "  Merchant: {$merchant}\n"
            . "  Amount: \${$amount}\n"
            . "  Date: {$date}\n"
            . "  Plaid Category Hint: {$hint}";
    }
}
