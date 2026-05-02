<?php

namespace Tests\Unit\Models;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_plaid_connection(): void
    {
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        $this->assertInstanceOf(PlaidConnection::class, $account->plaidConnection);
        $this->assertEquals($connection->id, $account->plaidConnection->id);
    }

    public function test_has_many_transactions(): void
    {
        $account = Account::factory()->create();
        Transaction::factory()->count(5)->create(['account_id' => $account->id]);

        $this->assertCount(5, $account->transactions);
        $this->assertInstanceOf(Transaction::class, $account->transactions->first());
    }

    public function test_casts_type_to_enum(): void
    {
        $account = Account::factory()->create([
            'type' => AccountType::Checking,
        ]);

        $fresh = Account::find($account->id);

        $this->assertInstanceOf(AccountType::class, $fresh->type);
        $this->assertSame(AccountType::Checking, $fresh->type);
    }
}
