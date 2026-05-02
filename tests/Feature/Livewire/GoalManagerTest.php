<?php

namespace Tests\Feature\Livewire;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_goals_with_progress(): void
    {
        $user = User::factory()->create();

        Goal::factory()->create([
            'name' => 'Emergency Fund',
            'target_amount' => 20000,
            'current_amount' => 5000,
            'is_completed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->assertSee('Emergency Fund')
            ->assertSee('20,000.00')
            ->assertSee('5,000.00')
            ->assertSee('25%');
    }

    public function test_can_create_goal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->set('name', 'New Car')
            ->set('targetAmount', '15000')
            ->set('currentAmount', '2000')
            ->set('priority', 'high')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('goals', [
            'name' => 'New Car',
            'target_amount' => 15000,
            'current_amount' => 2000,
            'priority' => 'high',
        ]);
    }

    public function test_can_update_goal_progress(): void
    {
        $user = User::factory()->create();

        $goal = Goal::factory()->create([
            'name' => 'Vacation Fund',
            'target_amount' => 5000,
            'current_amount' => 1000,
            'is_completed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->call('edit', $goal->id)
            ->set('currentAmount', '2500')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'current_amount' => 2500,
        ]);
    }

    public function test_can_mark_goal_complete(): void
    {
        $user = User::factory()->create();

        $goal = Goal::factory()->create([
            'name' => 'Pay Off Debt',
            'is_completed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->call('toggleComplete', $goal->id);

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'is_completed' => true,
        ]);
    }

    public function test_can_delete_goal(): void
    {
        $user = User::factory()->create();

        $goal = Goal::factory()->create([
            'name' => 'Old Goal',
            'is_completed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->call('delete', $goal->id);

        $this->assertDatabaseMissing('goals', ['id' => $goal->id]);
    }

    public function test_shows_monthly_savings_needed(): void
    {
        $user = User::factory()->create();

        Goal::factory()->create([
            'name' => 'Home Renovation',
            'target_amount' => 12000,
            'current_amount' => 0,
            'target_date' => now()->addMonths(12)->toDateString(),
            'is_completed' => false,
        ]);

        Livewire::actingAs($user)
            ->test('goals.goal-manager')
            ->assertSee('1,000.00');
    }
}
