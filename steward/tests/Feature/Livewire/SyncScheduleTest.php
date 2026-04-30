<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Settings\SyncSchedule;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SyncScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_current_schedule(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('sync_schedule', ['hour' => 3, 'minute' => 30]);

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->assertSet('hour', 3)
            ->assertSet('minute', 30);
    }

    public function test_can_update_schedule(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->set('hour', 6)
            ->set('minute', 0)
            ->call('save');

        $schedule = AppSetting::getValue('sync_schedule');
        $this->assertEquals(6, $schedule['hour']);
        $this->assertEquals(0, $schedule['minute']);
    }

    public function test_displays_confidence_threshold(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('categorization_confidence_threshold', 0.85);

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->assertSet('confidenceThreshold', 0.85);
    }

    public function test_can_update_confidence_threshold(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SyncSchedule::class)
            ->set('confidenceThreshold', 0.75)
            ->call('save');

        $threshold = AppSetting::getValue('categorization_confidence_threshold');
        $this->assertEquals(0.75, $threshold);
    }
}
