<?php

namespace Tests\Feature\Jobs;

use App\Enums\BudgetBucket;
use App\Jobs\CategorizationJob;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CategorizationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_categorizes_transactions_above_threshold(): void
    {
        $category = Category::factory()->create([
            'name' => 'Coffee',
            'default_bucket' => BudgetBucket::Wants,
            'is_essential' => false,
        ]);

        $transaction = Transaction::factory()->create([
            'merchant_name' => 'Starbucks',
            'amount' => 5.50,
            'date' => '2026-04-15',
            'plaid_category' => 'FOOD_AND_DRINK',
            'category_id' => null,
            'needs_review' => true,
        ]);

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'category_name' => 'Coffee',
                'confidence' => 0.95,
                'budget_bucket' => 'wants',
            ]);

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildCategorizationPrompt')
            ->once()
            ->andReturn('You are a categorization assistant...');

        $job = new CategorizationJob([$transaction->id]);
        $job->handle($mockOllama, $mockContext);

        $transaction->refresh();

        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertEquals('0.95', $transaction->categorization_confidence);
        $this->assertFalse($transaction->needs_review);
        $this->assertEquals(BudgetBucket::Wants, $transaction->budget_bucket);
    }

    public function test_flags_transactions_below_threshold(): void
    {
        $category = Category::factory()->create([
            'name' => 'Coffee',
            'default_bucket' => BudgetBucket::Wants,
            'is_essential' => false,
        ]);

        $transaction = Transaction::factory()->create([
            'merchant_name' => 'Unknown Cafe',
            'amount' => 3.75,
            'date' => '2026-04-15',
            'plaid_category' => null,
            'category_id' => null,
            'needs_review' => true,
        ]);

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'category_name' => 'Coffee',
                'confidence' => 0.45,
                'budget_bucket' => 'wants',
            ]);

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildCategorizationPrompt')
            ->once()
            ->andReturn('You are a categorization assistant...');

        $job = new CategorizationJob([$transaction->id]);
        $job->handle($mockOllama, $mockContext);

        $transaction->refresh();

        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertEquals('0.45', $transaction->categorization_confidence);
        $this->assertTrue($transaction->needs_review);
    }

    public function test_skips_already_categorized_transactions(): void
    {
        $category = Category::factory()->create([
            'name' => 'Groceries',
            'default_bucket' => BudgetBucket::Needs,
        ]);

        $transaction = Transaction::factory()->create([
            'category_id' => $category->id,
            'needs_review' => false,
        ]);

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldNotReceive('chatJson');

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildCategorizationPrompt')
            ->once()
            ->andReturn('You are a categorization assistant...');

        $job = new CategorizationJob([$transaction->id]);
        $job->handle($mockOllama, $mockContext);

        // No assertions needed beyond Mockery's shouldNotReceive verification
        $this->assertTrue(true);
    }
}
