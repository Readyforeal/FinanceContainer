<?php

namespace Tests\Feature\Livewire;

use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncomeSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_existing_income_sources(): void
    {
        $user = User::factory()->create();
        IncomeSource::factory()->create(['name' => 'Main Job', 'amount' => 3000, 'frequency' => 'monthly']);

        Livewire::actingAs($user)
            ->test('settings.income-sources')
            ->assertSee('Main Job');
    }

    public function test_can_add_income_source(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('settings.income-sources')
            ->set('name', 'Freelance Work')
            ->set('amount', 1500)
            ->set('frequency', 'monthly')
            ->set('nextPayDate', '2026-05-01')
            ->call('save');

        $this->assertDatabaseHas('income_sources', [
            'name' => 'Freelance Work',
            'frequency' => 'monthly',
        ]);
    }

    public function test_can_delete_income_source(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::factory()->create(['name' => 'Side Gig']);

        Livewire::actingAs($user)
            ->test('settings.income-sources')
            ->call('delete', $source->id);

        $this->assertDatabaseMissing('income_sources', ['id' => $source->id]);
    }

    public function test_shows_total_monthly_income(): void
    {
        $user = User::factory()->create();

        // $2400 biweekly = 2400 * 26 / 12 = $5200/mo
        IncomeSource::factory()->create(['name' => 'Job 1', 'amount' => 2400, 'frequency' => 'biweekly']);
        // $700 biweekly = 700 * 26 / 12 = $1516.67/mo
        IncomeSource::factory()->create(['name' => 'Job 2', 'amount' => 700, 'frequency' => 'biweekly']);
        // Total: $5200 + $1516.67 = $6716.67

        Livewire::actingAs($user)
            ->test('settings.income-sources')
            ->assertSee('6,716.67');
    }
}
