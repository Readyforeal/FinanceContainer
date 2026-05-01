<?php

namespace Tests\Feature\Livewire;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetRatiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_current_ratios(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('budget_ratios', ['needs' => 60, 'wants' => 25, 'savings' => 15]);

        Livewire::actingAs($user)
            ->test('settings.budget-ratios')
            ->assertSet('needs', 60)
            ->assertSet('wants', 25)
            ->assertSet('savings', 15);
    }

    public function test_can_update_ratios(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('settings.budget-ratios')
            ->set('needs', 50)
            ->set('wants', 30)
            ->set('savings', 20)
            ->call('save');

        $ratios = AppSetting::getValue('budget_ratios');
        $this->assertEquals(50, $ratios['needs']);
        $this->assertEquals(30, $ratios['wants']);
        $this->assertEquals(20, $ratios['savings']);
    }

    public function test_validates_ratios_sum_to_100(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('settings.budget-ratios')
            ->set('needs', 60)
            ->set('wants', 30)
            ->set('savings', 20)
            ->call('save')
            ->assertHasErrors('ratios');
    }
}
