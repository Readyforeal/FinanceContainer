<?php

namespace Tests\Unit\Models;

use App\Enums\PlaidConnectionStatus;
use App\Models\Account;
use App\Models\PlaidConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaidConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_many_accounts(): void
    {
        $connection = PlaidConnection::factory()->create();
        Account::factory()->count(3)->create(['plaid_connection_id' => $connection->id]);

        $this->assertCount(3, $connection->accounts);
        $this->assertInstanceOf(Account::class, $connection->accounts->first());
    }

    public function test_casts_status_to_enum(): void
    {
        $connection = PlaidConnection::factory()->create([
            'status' => PlaidConnectionStatus::Active,
        ]);

        $fresh = PlaidConnection::find($connection->id);

        $this->assertInstanceOf(PlaidConnectionStatus::class, $fresh->status);
        $this->assertSame(PlaidConnectionStatus::Active, $fresh->status);
    }

    public function test_encrypts_access_token(): void
    {
        $plainToken = 'access-sandbox-abc123';

        $connection = PlaidConnection::factory()->create([
            'access_token' => $plainToken,
        ]);

        // The raw DB value should be encrypted (not equal to the plain text)
        $rawValue = \DB::table('plaid_connections')
            ->where('id', $connection->id)
            ->value('access_token');

        $this->assertNotEquals($plainToken, $rawValue);

        // But reading through the model should decrypt it
        $fresh = PlaidConnection::find($connection->id);
        $this->assertEquals($plainToken, $fresh->access_token);
    }
}
