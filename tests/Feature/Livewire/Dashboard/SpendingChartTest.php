<?php

namespace Tests\Feature\Livewire\Dashboard;

use App\Models\Account;
use App\Models\PlaidConnection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpendingChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_provides_chart_data_for_last_7_days(): void
    {
        $user = User::factory()->create();
        $connection = PlaidConnection::factory()->create();
        $account = Account::factory()->create(['plaid_connection_id' => $connection->id]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'amount' => 75.00,
            'date' => now()->toDateString(),
        ]);

        $component = Livewire::actingAs($user)
            ->test('dashboard.spending-chart');

        $component->assertSet('days', 7);

        $chartValues = $component->viewData('chartValues');
        $this->assertNotEmpty($chartValues);
        $this->assertCount(7, $chartValues);
    }

    public function test_can_toggle_to_30_days(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('dashboard.spending-chart')
            ->call('setDays', 30)
            ->assertSet('days', 30);
    }
}
