<?php

namespace Tests\Feature\Livewire;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_existing_recipients(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('email_recipients', ['alice@example.com', 'bob@example.com']);

        Livewire::actingAs($user)
            ->test('settings.email-recipients')
            ->assertSee('alice@example.com')
            ->assertSee('bob@example.com');
    }

    public function test_can_add_recipient(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('settings.email-recipients')
            ->set('newEmail', 'newuser@example.com')
            ->call('addRecipient');

        $recipients = AppSetting::getValue('email_recipients');
        $this->assertContains('newuser@example.com', $recipients);
    }

    public function test_can_remove_recipient(): void
    {
        $user = User::factory()->create();
        AppSetting::setValue('email_recipients', ['first@example.com', 'second@example.com']);

        Livewire::actingAs($user)
            ->test('settings.email-recipients')
            ->call('removeRecipient', 0);

        $recipients = AppSetting::getValue('email_recipients');
        $this->assertNotContains('first@example.com', $recipients);
        $this->assertContains('second@example.com', $recipients);
    }
}
