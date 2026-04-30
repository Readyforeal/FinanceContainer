<?php

namespace Tests\Feature\Jobs;

use App\Enums\BudgetBucket;
use App\Jobs\SummaryJob;
use App\Mail\DailySummaryMail;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\IncomeSource;
use App\Models\Summary;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SummaryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_daily_summary(): void
    {
        $account = Account::factory()->create();

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 75.00,
            'date' => now()->toDateString(),
            'budget_bucket' => BudgetBucket::Wants,
        ]);

        IncomeSource::factory()->create([
            'amount' => 4000,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chat')
            ->once()
            ->andReturn('You spent $75 on wants today. This is within your budget targets.');

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new SummaryJob('daily');
        $job->handle($mockOllama, $mockContext);

        $summary = Summary::where('type', 'daily')->first();

        $this->assertNotNull($summary);
        $this->assertEquals('75.00', $summary->total_spent);
        $this->assertEquals('0.00', $summary->needs_spent);
        $this->assertEquals('75.00', $summary->wants_spent);
        $this->assertEquals('0.00', $summary->savings_spent);
        $this->assertStringContainsString('$75', $summary->ai_analysis);
    }

    public function test_creates_weekly_summary(): void
    {
        $account = Account::factory()->create();

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 120.00,
            'date' => now()->startOfWeek()->addDays(2)->toDateString(),
            'budget_bucket' => BudgetBucket::Needs,
        ]);

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chat')
            ->once()
            ->andReturn('Weekly needs spending of $120 noted.');

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new SummaryJob('weekly');
        $job->handle($mockOllama, $mockContext);

        $summary = Summary::where('type', 'weekly')->first();

        $this->assertNotNull($summary);
        $this->assertEquals('120.00', $summary->needs_spent);
        $this->assertEquals('0.00', $summary->wants_spent);
    }

    public function test_sends_summary_email(): void
    {
        Mail::fake();

        AppSetting::setValue('email_recipients', ['alice@example.com', 'bob@example.com']);

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chat')
            ->once()
            ->andReturn('Daily summary analysis.');

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new SummaryJob('daily');
        $job->handle($mockOllama, $mockContext);

        Mail::assertSent(DailySummaryMail::class, 2);
    }
}
