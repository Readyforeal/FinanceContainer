<?php

namespace Tests\Feature\Livewire;

use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountListTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_accounts(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'name' => 'My Checking',
            'current_balance' => 1234.56,
        ]);

        Livewire::actingAs($user)
            ->test('accounts.account-list')
            ->assertSee('My Checking')
            ->assertSee('1,234.56');
    }

    public function test_shows_plaid_link_when_no_connections(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('accounts.account-list')
            ->assertSeeLivewire('plaid.plaid-link');
    }

    public function test_refreshes_on_plaid_connected_event(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('accounts.account-list');

        // No connections initially
        $component->assertSeeLivewire('plaid.plaid-link');

        // Simulate a connection being created and event dispatched
        $connection = PlaidConnection::factory()->create();
        Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'name' => 'New Account',
        ]);

        $component->dispatch('plaid-connected')
            ->assertSee('New Account');
    }
}
