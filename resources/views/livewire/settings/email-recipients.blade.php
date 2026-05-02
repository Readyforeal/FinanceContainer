<?php

use App\Models\AppSetting;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|email')]
    public string $newEmail = '';

    public function addRecipient(): void
    {
        $this->validate();

        $recipients = AppSetting::getValue('email_recipients', []);
        $recipients[] = $this->newEmail;
        AppSetting::setValue('email_recipients', $recipients);

        $this->newEmail = '';
        $this->resetValidation();
    }

    public function removeRecipient(int $index): void
    {
        $recipients = AppSetting::getValue('email_recipients', []);
        array_splice($recipients, $index, 1);
        AppSetting::setValue('email_recipients', array_values($recipients));
    }

    public function with(): array
    {
        return [
            'recipients' => AppSetting::getValue('email_recipients', []),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">Email Recipients</flux:heading>
        <flux:icon.mail class="size-5 text-zinc-400 dark:text-zinc-500" />
    </div>

    <flux:card class="p-5">
        <flux:text size="sm" class="mb-5">
            Manage email addresses that receive financial summaries and alerts.
        </flux:text>

        {{-- Existing recipients --}}
        @if (count($recipients) > 0)
            <div class="space-y-2 mb-5">
                @foreach ($recipients as $index => $email)
                    <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <flux:icon.mail class="size-4 text-zinc-400 dark:text-zinc-500" />
                            <flux:text size="sm">{{ $email }}</flux:text>
                        </div>
                        <flux:button
                            wire:click="removeRecipient({{ $index }})"
                            variant="subtle"
                            size="xs"
                            icon="trash-2"
                            title="Remove"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-6 mb-5">
                <flux:icon.mail class="size-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500">No email recipients added yet.</flux:text>
            </div>
        @endif

        {{-- Add form --}}
        <form wire:submit="addRecipient" class="flex items-start gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model="newEmail"
                    type="email"
                    placeholder="email@example.com"
                />
                @error('newEmail') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
            </div>
            <flux:button type="submit" variant="primary" icon="plus">
                Add Recipient
            </flux:button>
        </form>
    </flux:card>
</div>
