<?php
namespace Tests\Feature\Jobs;

use App\Enums\AccountType;
use App\Enums\PlaidConnectionStatus;
use App\Jobs\PlaidSyncJob;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Services\PlaidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlaidSyncJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_new_transactions(): void
    {
        $connection = PlaidConnection::factory()->create([
            'cursor' => null,
            'status' => PlaidConnectionStatus::Active,
        ]);

        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_001',
            'type' => AccountType::Checking,
        ]);

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('syncTransactions')
            ->once()
            ->with($connection->access_token, null)
            ->andReturn([
                'added' => [
                    [
                        'transaction_id' => 'txn_001',
                        'account_id' => 'acc_001',
                        'amount' => -12.50,
                        'date' => '2026-04-28',
                        'merchant_name' => 'Starbucks',
                        'name' => 'STARBUCKS COFFEE',
                        'personal_finance_category' => ['primary' => 'FOOD_AND_DRINK'],
                    ],
                ],
                'modified' => [],
                'removed' => [],
                'next_cursor' => 'cursor_abc',
                'has_more' => false,
            ]);

        $mock->shouldReceive('getAccounts')
            ->once()
            ->andReturn([
                [
                    'account_id' => 'acc_001',
                    'name' => 'Checking',
                    'type' => 'depository',
                    'subtype' => 'checking',
                    'balances' => ['current' => 1247.33, 'available' => 1200.00],
                ],
            ]);

        $this->app->instance(PlaidService::class, $mock);

        (new PlaidSyncJob($connection))->handle($mock);

        $this->assertDatabaseHas('transactions', [
            'plaid_transaction_id' => 'txn_001',
            'account_id' => $account->id,
            'amount' => 12.50,
            'merchant_name' => 'Starbucks',
            'description' => 'STARBUCKS COFFEE',
            'needs_review' => true,
        ]);

        $connection->refresh();
        $this->assertEquals('cursor_abc', $connection->cursor);

        $account->refresh();
        $this->assertEquals('1247.33', $account->current_balance);
        $this->assertEquals('1200.00', $account->available_balance);
    }

    public function test_handles_removed_transactions(): void
    {
        $connection = PlaidConnection::factory()->create(['cursor' => 'old_cursor']);
        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_001',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'plaid_transaction_id' => 'txn_to_remove',
        ]);

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('syncTransactions')
            ->once()
            ->andReturn([
                'added' => [],
                'modified' => [],
                'removed' => [['transaction_id' => 'txn_to_remove']],
                'next_cursor' => 'new_cursor',
                'has_more' => false,
            ]);
        $mock->shouldReceive('getAccounts')->once()->andReturn([]);

        (new PlaidSyncJob($connection))->handle($mock);

        $this->assertDatabaseMissing('transactions', ['plaid_transaction_id' => 'txn_to_remove']);
    }

    public function test_paginates_with_has_more(): void
    {
        $connection = PlaidConnection::factory()->create(['cursor' => null]);
        $account = Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'plaid_account_id' => 'acc_001',
        ]);

        $mock = Mockery::mock(PlaidService::class);
        $mock->shouldReceive('syncTransactions')
            ->with($connection->access_token, null)
            ->once()
            ->andReturn([
                'added' => [['transaction_id' => 'txn_page1', 'account_id' => 'acc_001', 'amount' => -10, 'date' => '2026-04-28', 'merchant_name' => 'Store A', 'name' => 'STORE A', 'personal_finance_category' => null]],
                'modified' => [], 'removed' => [],
                'next_cursor' => 'cursor_page2', 'has_more' => true,
            ]);
        $mock->shouldReceive('syncTransactions')
            ->with($connection->access_token, 'cursor_page2')
            ->once()
            ->andReturn([
                'added' => [['transaction_id' => 'txn_page2', 'account_id' => 'acc_001', 'amount' => -20, 'date' => '2026-04-28', 'merchant_name' => 'Store B', 'name' => 'STORE B', 'personal_finance_category' => null]],
                'modified' => [], 'removed' => [],
                'next_cursor' => 'cursor_final', 'has_more' => false,
            ]);
        $mock->shouldReceive('getAccounts')->once()->andReturn([]);

        (new PlaidSyncJob($connection))->handle($mock);

        $this->assertEquals(2, Transaction::count());
        $connection->refresh();
        $this->assertEquals('cursor_final', $connection->cursor);
    }
}
