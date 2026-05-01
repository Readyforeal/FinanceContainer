<?php

use App\Enums\AccountType;
use App\Models\Account;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:checking,savings')]
    public string $type = 'checking';

    #[Validate('required|numeric|min:0')]
    public string $balance = '0';

    public bool $showForm = false;

    public function save(): void
    {
        $this->validate();

        Account::create([
            'name' => $this->name,
            'type' => $this->type,
            'plaid_account_id' => 'manual_' . uniqid(),
            'plaid_connection_id' => $this->getOrCreateManualConnection(),
            'current_balance' => (float) $this->balance,
            'available_balance' => (float) $this->balance,
        ]);

        $this->reset(['name', 'type', 'balance', 'showForm']);
        $this->type = 'checking';
        $this->dispatch('account-created');
    }

    private function getOrCreateManualConnection(): int
    {
        $connection = \App\Models\PlaidConnection::where('item_id', 'manual')->first();

        if (! $connection) {
            $connection = \App\Models\PlaidConnection::create([
                'access_token' => 'manual',
                'item_id' => 'manual',
                'institution_name' => 'Manual Accounts',
                'status' => 'active',
            ]);
        }

        return $connection->id;
    }
};
?>

<div>
    @if (! $showForm)
        <button wire:click="$toggle('showForm')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">
            <x-lucide-plus class="w-4 h-4" />
            Add Account
        </button>
    @else
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Add Account</h3>

            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Account Name</label>
                        <input type="text" wire:model="name" placeholder="e.g. Main Checking"
                            class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-zinc-200 placeholder-zinc-400" />
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Type</label>
                        <select wire:model="type"
                            class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-zinc-200">
                            <option value="checking">Checking</option>
                            <option value="savings">Savings</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Current Balance</label>
                        <input type="number" step="0.01" wire:model="balance" placeholder="0.00"
                            class="w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-zinc-900 dark:text-zinc-200" />
                        @error('balance') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">
                        Save Account
                    </button>
                    <button type="button" wire:click="$toggle('showForm')" class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
