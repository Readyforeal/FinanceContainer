<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Services\FinancialContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private FinancialContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FinancialContextBuilder();
    }

    public function test_system_prompt_includes_income_sources(): void
    {
        IncomeSource::factory()->create([
            'name' => 'Primary Salary',
            'amount' => 3000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        IncomeSource::factory()->create([
            'name' => 'Side Consulting',
            'amount' => 1000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('Primary Salary', $prompt);
        $this->assertStringContainsString('Side Consulting', $prompt);
        $this->assertStringContainsString('3,000.00', $prompt);
        $this->assertStringContainsString('1,000.00', $prompt);
    }

    public function test_system_prompt_includes_budget_ratios(): void
    {
        AppSetting::setValue('budget_ratios', [
            'needs' => 50,
            'wants' => 30,
            'savings' => 20,
        ]);

        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('50%', $prompt);
        $this->assertStringContainsString('30%', $prompt);
        $this->assertStringContainsString('20%', $prompt);
    }

    public function test_system_prompt_includes_categories_with_essential_flag(): void
    {
        Category::factory()->create([
            'name' => 'Groceries',
            'is_essential' => true,
            'default_bucket' => 'needs',
        ]);

        Category::factory()->create([
            'name' => 'Entertainment',
            'is_essential' => false,
            'default_bucket' => 'wants',
        ]);

        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('Groceries', $prompt);
        $this->assertStringContainsString('Entertainment', $prompt);
        $this->assertStringContainsString('essential', $prompt);
    }

    public function test_system_prompt_includes_behavioral_rules(): void
    {
        $prompt = $this->builder->buildSystemPrompt();

        $this->assertStringContainsString('biblical', $prompt);
        $this->assertStringContainsString('stewardship', $prompt);
        $this->assertStringContainsString('discretionary', $prompt);
    }

    public function test_dynamic_context_includes_account_balances(): void
    {
        Account::factory()->create([
            'name' => 'Primary Checking',
            'current_balance' => 2500.75,
            'available_balance' => 2400.00,
        ]);

        $context = $this->builder->buildDynamicContext();

        $this->assertStringContainsString('Primary Checking', $context);
        $this->assertStringContainsString('2,500.75', $context);
    }

    public function test_dynamic_context_includes_recent_transactions(): void
    {
        $account = Account::factory()->create();

        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Whole Foods Market',
            'amount' => 87.43,
            'date' => now()->subDays(5),
        ]);

        $context = $this->builder->buildDynamicContext();

        $this->assertStringContainsString('Whole Foods Market', $context);
        $this->assertStringContainsString('87.43', $context);
    }

    public function test_dynamic_context_includes_budget_vs_actual(): void
    {
        $category = Category::factory()->create(['name' => 'Dining Out']);

        Budget::factory()->create([
            'category_id' => $category->id,
            'month' => now()->format('Y-m'),
            'budgeted_amount' => 300.00,
        ]);

        $account = Account::factory()->create();

        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 125.50,
            'date' => now()->startOfMonth()->addDays(5),
        ]);

        $context = $this->builder->buildDynamicContext();

        $this->assertStringContainsString('300.00', $context);
        $this->assertStringContainsString('125.50', $context);
    }

    public function test_categorization_prompt_lists_available_categories(): void
    {
        Category::factory()->create([
            'name' => 'Utilities',
            'is_essential' => true,
            'default_bucket' => 'needs',
        ]);

        Category::factory()->create([
            'name' => 'Streaming Services',
            'is_essential' => false,
            'default_bucket' => 'wants',
        ]);

        $prompt = $this->builder->buildCategorizationPrompt();

        $this->assertStringContainsString('Utilities', $prompt);
        $this->assertStringContainsString('Streaming Services', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('confidence', $prompt);
    }
}
