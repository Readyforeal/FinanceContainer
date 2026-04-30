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
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Email Recipients</h2>
        <x-lucide-mail class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-5">
            Manage email addresses that receive financial summaries and alerts.
        </p>

        {{-- Existing recipients --}}
        @if (count($recipients) > 0)
            <div class="space-y-2 mb-5">
                @foreach ($recipients as $index => $email)
                    <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <x-lucide-mail class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $email }}</span>
                        </div>
                        <button
                            wire:click="removeRecipient({{ $index }})"
                            class="p-1 text-zinc-400 dark:text-zinc-500 hover:text-red-500 transition-colors"
                            title="Remove"
                        >
                            <x-lucide-x class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-6 text-zinc-400 dark:text-zinc-500 mb-5">
                <x-lucide-mail class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm">No email recipients added yet.</p>
            </div>
        @endif

        {{-- Add form --}}
        <form wire:submit="addRecipient" class="flex items-start gap-3">
            <div class="flex-1">
                <input
                    wire:model="newEmail"
                    type="email"
                    placeholder="email@example.com"
                    class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-zinc-400 dark:placeholder-zinc-500"
                />
                @error('newEmail') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <button
                type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors whitespace-nowrap"
            >
                Add Recipient
            </button>
        </form>
    </div>
</div>
