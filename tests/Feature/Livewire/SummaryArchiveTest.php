<?php

namespace Tests\Feature\Livewire;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SummaryArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_daily_summaries(): void
    {
        $user = User::factory()->create();

        Summary::create([
            'type' => 'daily',
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'total_spent' => 125.50,
            'needs_spent' => 80.00,
            'wants_spent' => 30.00,
            'savings_spent' => 15.50,
            'total_income' => 0,
            'ai_analysis' => 'You spent well within your limits today.',
            'ai_advice' => null,
            'habit_flags' => [],
        ]);

        Livewire::actingAs($user)
            ->test('summaries.summary-archive')
            ->assertSee('125')
            ->assertSee('You spent well within your limits today.');
    }

    public function test_can_switch_tabs(): void
    {
        $user = User::factory()->create();

        Summary::create([
            'type' => 'weekly',
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'total_spent' => 540.00,
            'needs_spent' => 300.00,
            'wants_spent' => 180.00,
            'savings_spent' => 60.00,
            'total_income' => 0,
            'ai_analysis' => 'Weekly summary analysis here.',
            'ai_advice' => null,
            'habit_flags' => [],
        ]);

        Livewire::actingAs($user)
            ->test('summaries.summary-archive')
            ->call('setTab', 'weekly')
            ->assertSet('activeTab', 'weekly')
            ->assertSee('540');
    }

    public function test_shows_empty_state(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('summaries.summary-archive')
            ->assertSee('No summaries yet');
    }
}
