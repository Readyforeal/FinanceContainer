<?php

namespace App\Jobs;

use App\Mail\PaymentReminderMail;
use App\Models\AppSetting;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class PaymentOptimizerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('ai');
    }

    public function handle(OllamaService $ollama, FinancialContextBuilder $contextBuilder): array
    {
        $systemPrompt = $contextBuilder->buildSystemPrompt();
        $dynamicContext = $contextBuilder->buildDynamicContext();

        $userMessage = <<<TEXT
Please analyze the essential bills and recurring payments below and suggest optimal payment timing to avoid late fees and maintain healthy cash flow.

Return your response as JSON with this exact structure:
{
  "recommendations": [
    {
      "bill": "<bill name>",
      "suggested_pay_date": "<YYYY-MM-DD>",
      "reason": "<brief explanation>"
    }
  ],
  "analysis": "<overall payment timing analysis>"
}

CURRENT FINANCIAL CONTEXT:
{$dynamicContext}
TEXT;

        $result = $ollama->chatJson($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ]);

        $recommendations = $result['recommendations'] ?? [];
        $analysis = $result['analysis'] ?? '';

        $today = now()->toDateString();
        $todaysRecs = array_filter($recommendations, fn ($rec) => ($rec['suggested_pay_date'] ?? '') === $today);

        if (!empty($todaysRecs)) {
            $recipients = AppSetting::getValue('email_recipients', []);

            foreach ($recipients as $email) {
                Mail::to($email)->send(new PaymentReminderMail(array_values($todaysRecs), $analysis));
            }
        }

        return $result;
    }
}
