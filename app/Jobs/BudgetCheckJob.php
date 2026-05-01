<?php

namespace App\Jobs;

use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BudgetCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): string
    {
        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<TEXT
Please perform a comprehensive budget analysis using the financial data provided below.

Focus on:
1. How actual spending compares to the 50/30/20 (needs/wants/savings) budget targets
2. Categories that are over or under budget this month
3. Specific actionable recommendations to improve alignment with budget targets
4. Any concerning spending trends that need immediate attention
5. Biblical stewardship perspective on the current spending patterns

CURRENT FINANCIAL CONTEXT:
{$dynamicContext}
TEXT;

        return $ollama->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ]);
    }
}
