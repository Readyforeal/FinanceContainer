<?php

namespace Tests\Feature\Livewire;

use App\Models\User;
use App\Services\PlaidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlaidLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_link_token(): void
    {
        $user = User::factory()->create();

        $mock = $this->mock(PlaidService::class);
        $mock->shouldReceive('createLinkToken')
            ->once()
            ->with((string) $user->id)
            ->andReturn(['link_token' => 'link-sandbox-test-token-123']);

        Livewire::actingAs($user)
            ->test('plaid.plaid-link')
            ->call('createLinkToken')
            ->assertSet('linkToken', 'link-sandbox-test-token-123')
            ->assertSet('connecting', true);
    }

    public function test_can_exchange_token_and_create_connection(): void
    {
        $user = User::factory()->create();

        $mock = $this->mock(PlaidService::class);
        $mock->shouldReceive('exchangePublicToken')
            ->once()
            ->with('public-sandbox-token')
            ->andReturn([
                'access_token' => 'access-sandbox-abc123',
                'item_id' => 'item_abc123',
            ]);

        $mock->shouldReceive('getAccounts')
            ->once()
            ->with('access-sandbox-abc123')
            ->andReturn([
                [
                    'account_id' => 'acc_001',
                    'name' => 'Checking Account',
                    'subtype' => 'checking',
                    'balances' => [
                        'current' => 1234.56,
                        'available' => 1200.00,
                    ],
                ],
                [
                    'account_id' => 'acc_002',
                    'name' => 'Savings Account',
                    'subtype' => 'savings',
                    'balances' => [
                        'current' => 5678.90,
                        'available' => 5678.90,
                    ],
                ],
            ]);

        Livewire::actingAs($user)
            ->test('plaid.plaid-link')
            ->call('onSuccess', 'public-sandbox-token', [
                'institution' => ['name' => 'Chase'],
            ]);

        $this->assertDatabaseHas('plaid_connections', [
            'item_id' => 'item_abc123',
            'institution_name' => 'Chase',
        ]);

        $this->assertDatabaseCount('accounts', 2);
        $this->assertDatabaseHas('accounts', ['plaid_account_id' => 'acc_001', 'name' => 'Checking Account']);
        $this->assertDatabaseHas('accounts', ['plaid_account_id' => 'acc_002', 'name' => 'Savings Account']);
    }
}
