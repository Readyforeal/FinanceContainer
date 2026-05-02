<?php

namespace Tests\Feature\Livewire;

use App\Enums\BudgetBucket;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_budgets_with_spent_and_remaining(): void
    {
        $user = User::factory()->create();

        IncomeSource::factory()->create([
            'amount' => 5000,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $category = Category::factory()->create([
            'name' => 'Groceries',
            'default_bucket' => BudgetBucket::Needs,
        ]);

        $budget = Budget::factory()->create([
            'category_id' => $category->id,

            'budgeted_amount' => 400,
            'bucket' => BudgetBucket::Needs,
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'amount' => -150,
            'date' => now()->startOfMonth(),
        ]);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->assertSee('Groceries')
            ->assertSee('400')
            ->assertSee('150');
    }

    public function test_shows_percentage_of_income(): void
    {
        $user = User::factory()->create();

        IncomeSource::factory()->create([
            'amount' => 5000,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $category = Category::factory()->create([
            'name' => 'Rent',
            'default_bucket' => BudgetBucket::Needs,
        ]);

        Budget::factory()->create([
            'category_id' => $category->id,

            'budgeted_amount' => 1500,
            'bucket' => BudgetBucket::Needs,
        ]);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->assertSee('30%');
    }

    public function test_can_create_budget(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'Entertainment',
            'default_bucket' => BudgetBucket::Wants,
        ]);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->set('editCategoryId', $category->id)
            ->set('editAmount', '250')
            ->call('saveBudget')
            ->assertSet('editAmount', '');

        $this->assertDatabaseHas('budgets', [
            'category_id' => $category->id,
            'budgeted_amount' => 250,
        ]);
    }

    public function test_warns_when_total_exceeds_income(): void
    {
        $user = User::factory()->create();

        IncomeSource::factory()->create([
            'amount' => 2000,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $category1 = Category::factory()->create(['default_bucket' => BudgetBucket::Needs]);
        $category2 = Category::factory()->create(['default_bucket' => BudgetBucket::Wants]);

        Budget::factory()->create([
            'category_id' => $category1->id,

            'budgeted_amount' => 1500,
            'bucket' => BudgetBucket::Needs,
        ]);

        Budget::factory()->create([
            'category_id' => $category2->id,

            'budgeted_amount' => 800,
            'bucket' => BudgetBucket::Wants,
        ]);

        Livewire::actingAs($user)
            ->test('budgets.budget-manager')
            ->assertSee('over income');
    }
}
