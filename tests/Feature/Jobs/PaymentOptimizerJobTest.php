<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PaymentOptimizerJob;
use App\Mail\PaymentReminderMail;
use App\Models\AppSetting;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PaymentOptimizerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_payment_calendar(): void
    {
        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'recommendations' => [
                    [
                        'bill' => 'Electric Bill',
                        'suggested_pay_date' => '2026-05-15',
                        'reason' => 'Pay before due date to avoid late fees.',
                    ],
                ],
                'analysis' => 'Your bills are well-timed to avoid cash flow issues.',
            ]);

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new PaymentOptimizerJob();
        $result = $job->handle($mockOllama, $mockContext);

        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('analysis', $result);
        $this->assertIsArray($result['recommendations']);
        $this->assertNotEmpty($result['recommendations']);
    }

    public function test_sends_payment_reminder_emails(): void
    {
        Mail::fake();

        AppSetting::setValue('email_recipients', ['alice@example.com', 'bob@example.com']);

        $today = now()->toDateString();

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'recommendations' => [
                    [
                        'bill' => 'Internet Bill',
                        'suggested_pay_date' => $today,
                        'reason' => 'Due today.',
                    ],
                ],
                'analysis' => 'You have a payment due today.',
            ]);

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new PaymentOptimizerJob();
        $job->handle($mockOllama, $mockContext);

        Mail::assertSent(PaymentReminderMail::class, 2);
    }
}
