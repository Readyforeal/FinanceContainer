<?php

namespace Tests\Feature\Livewire;

use App\Models\Account;
use App\Models\Category;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_transactions(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Whole Foods',
            'amount' => 45.67,
        ]);

        Livewire::actingAs($user)
            ->test('transactions.transaction-list')
            ->assertSee('Whole Foods')
            ->assertSee('45.67');
    }

    public function test_filters_by_account(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account1 = Account::factory()->create(['plaid_connection_id' => $connection->id, 'name' => 'Checking']);
        $account2 = Account::factory()->create(['plaid_connection_id' => $connection->id, 'name' => 'Savings']);

        Transaction::factory()->create(['account_id' => $account1->id, 'merchant_name' => 'Amazon']);
        Transaction::factory()->create(['account_id' => $account2->id, 'merchant_name' => 'Netflix']);

        Livewire::actingAs($user)
            ->test('transactions.transaction-list')
            ->set('accountFilter', $account1->id)
            ->assertSee('Amazon')
            ->assertDontSee('Netflix');
    }

    public function test_filters_by_needs_review(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Flagged Merchant',
            'needs_review' => true,
        ]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'merchant_name' => 'Normal Merchant',
            'needs_review' => false,
        ]);

        Livewire::actingAs($user)
            ->test('transactions.transaction-list')
            ->set('reviewFilter', true)
            ->assertSee('Flagged Merchant')
            ->assertDontSee('Normal Merchant');
    }

    public function test_can_assign_category(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'needs_review' => true,
            'category_id' => null,
        ]);
        $category = Category::factory()->create(['name' => 'Groceries']);

        Livewire::actingAs($user)
            ->test('transactions.transaction-list')
            ->call('assignCategory', $transaction->id, $category->id);

        $transaction->refresh();
        $this->assertEquals($category->id, $transaction->category_id);
        $this->assertFalse($transaction->needs_review);
        $this->assertEquals('1.00', $transaction->categorization_confidence);
    }
}
