<?php

namespace Tests\Feature\Livewire;

use App\Models\Account;
use App\Models\Goal;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_income_summary(): void
    {
        $user = User::factory()->create();

        IncomeSource::factory()->create([
            'name' => 'Primary Job',
            'amount' => 3500.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        IncomeSource::factory()->create([
            'name' => 'Side Work',
            'amount' => 1500.00,
            'frequency' => 'biweekly',
            'is_active' => true,
        ]);

        // $3500 monthly + $1500 biweekly (1500 * 26/12 = 3250.00) = 6750.00
        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('Primary Job')
            ->assertSee('Side Work')
            ->assertSee('6,750.00');
    }

    public function test_displays_net_monthly_position(): void
    {
        $user = User::factory()->create();

        IncomeSource::factory()->create([
            'name' => 'Monthly Income',
            'amount' => 5000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('5,000.00');
    }

    public function test_displays_goals_overview(): void
    {
        $user = User::factory()->create();

        Goal::factory()->create([
            'name' => 'Dream Vacation',
            'target_amount' => 20000,
            'current_amount' => 5000,
            'is_completed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('Dream Vacation')
            ->assertSee('25%');
    }

    public function test_displays_annual_projection(): void
    {
        $user = User::factory()->create();

        IncomeSource::factory()->create([
            'name' => 'Salary',
            'amount' => 5000.00,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('profile.financial-profile')
            ->assertSee('60,000.00');
    }
}
