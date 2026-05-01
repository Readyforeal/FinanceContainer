<?php

namespace App\Jobs;

use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HealthCheckJob implements ShouldQueue
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
Please perform a comprehensive financial health check using the financial data provided below.

Cover the following areas:
1. Spending habits and behavioral patterns — identify both positive habits and areas of concern
2. Spending trends over time — are things improving or deteriorating?
3. Essential vs discretionary (needs vs wants) balance — recommend specific adjustments where needed
4. Goal achievability — based on current savings rate, assess whether financial goals are on track
5. Biblical stewardship recommendations — provide guidance grounded in the principle that money is a resource entrusted by God for provision, generosity, and purposeful living

Be direct and honest. Do not soften difficult feedback. Connect every major finding to its impact on the family's long-term financial health and giving capacity.

CURRENT FINANCIAL CONTEXT:
{$dynamicContext}
TEXT;

        return $ollama->chat($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ]);
    }
}
