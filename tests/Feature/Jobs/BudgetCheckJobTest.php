<?php

namespace Tests\Feature\Jobs;

use App\Jobs\BudgetCheckJob;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BudgetCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_budget_analysis(): void
    {
        AppSetting::setValue('budget_ratios', [
            'needs' => 50,
            'wants' => 30,
            'savings' => 20,
        ]);

        IncomeSource::factory()->create([
            'name' => 'Main Job',
            'amount' => 5000,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $category = Category::factory()->create([
            'name' => 'Groceries',
        ]);

        Budget::factory()->create([
            'category_id' => $category->id,
            'budgeted_amount' => 600,
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => 450,
            'date' => now()->toDateString(),
        ]);

        $expectedAnalysis = 'Budget analysis: You are spending well within your needs budget. Consider allocating more to savings.';

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chat')
            ->once()
            ->andReturn($expectedAnalysis);

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new BudgetCheckJob();
        $result = $job->handle($mockOllama, $mockContext);

        $this->assertIsString($result);
        $this->assertStringContainsString($expectedAnalysis, $result);
    }
}
