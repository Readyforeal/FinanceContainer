<?php

namespace Tests\Feature\Livewire\Dashboard;

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\PlaidConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BalanceCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_account_balances(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'name' => 'Main Checking',
            'current_balance' => 2500.00,
        ]);
        Account::factory()->create([
            'plaid_connection_id' => $connection->id,
            'name' => 'Emergency Savings',
            'current_balance' => 10000.00,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard.balance-cards')
            ->assertSee('Main Checking')
            ->assertSee('2,500.00')
            ->assertSee('Emergency Savings')
            ->assertSee('10,000.00');
    }

    public function test_displays_next_payday(): void
    {
        $user = User::factory()->create();
        $payDate = now()->addDays(3)->startOfDay();

        IncomeSource::factory()->create([
            'name' => 'My Job',
            'next_pay_date' => $payDate->toDateString(),
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('dashboard.balance-cards')
            ->assertSee('3 days');
    }

    public function test_shows_empty_state_when_no_accounts(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('dashboard.balance-cards')
            ->assertSee('No accounts connected');
    }
}
