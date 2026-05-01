<?php

namespace Tests\Feature\Livewire\Dashboard;

use App\Enums\BudgetBucket;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_bucket_percentages(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 500,
            'budget_bucket' => BudgetBucket::Needs,
            'date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 300,
            'budget_bucket' => BudgetBucket::Wants,
            'date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 200,
            'budget_bucket' => BudgetBucket::Savings,
            'date' => now()->startOfMonth(),
        ]);

        Livewire::actingAs($user)
            ->test('dashboard.budget-progress')
            ->assertSee('Needs')
            ->assertSee('Wants')
            ->assertSee('Savings');
    }

    public function test_shows_warning_when_over_target(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        // Wants at 60% of total spend (target is 30%)
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 400,
            'budget_bucket' => BudgetBucket::Needs,
            'date' => now()->startOfMonth(),
        ]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 600,
            'budget_bucket' => BudgetBucket::Wants,
            'date' => now()->startOfMonth(),
        ]);

        Livewire::actingAs($user)
            ->test('dashboard.budget-progress')
            ->assertSee('over');
    }
}
