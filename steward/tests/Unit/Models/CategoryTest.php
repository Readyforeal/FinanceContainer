<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_many_transactions(): void
    {
        $category = Category::factory()->create();
        $account = Account::factory()->create();

        Transaction::factory()->count(4)->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
        ]);

        $this->assertCount(4, $category->transactions);
        $this->assertInstanceOf(Transaction::class, $category->transactions->first());
    }

    public function test_has_many_budgets(): void
    {
        $category = Category::factory()->create();
        Budget::factory()->count(2)->create(['category_id' => $category->id]);

        $this->assertCount(2, $category->budgets);
        $this->assertInstanceOf(Budget::class, $category->budgets->first());
    }

    public function test_average_spend_calculation(): void
    {
        $category = Category::factory()->create();
        $account = Account::factory()->create();

        // Create transactions within the last 3 months
        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 300.00,
            'date' => now()->subMonth(),
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 300.00,
            'date' => now()->subMonths(2),
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'amount' => 300.00,
            'date' => now()->subMonths(3)->addDay(),
        ]);

        // Total = 900, divided by 3 months = 300
        $average = $category->averageSpend(3);

        $this->assertEquals(300.00, $average);
    }
}
