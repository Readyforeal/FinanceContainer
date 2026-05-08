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
            'current_balance' => (float) $this->balance,
            'available_balance' => (float) $this->balance,
        ]);

        $this->reset(['name', 'type', 'balance', 'showForm']);
        $this->type = 'checking';
        $this->dispatch('account-created');
    }
};
?>

<div>
    @if (! $showForm)
        <flux:button wire:click="$toggle('showForm')" variant="primary" icon="plus">
            Add Account
        </flux:button>
    @else
        <flux:card class="p-5">
            <flux:heading size="sm" level="3" class="mb-4">Add Account</flux:heading>

            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:input wire:model="name" label="Account Name" placeholder="e.g. Main Checking" />
                    <flux:select wire:model="type" label="Type">
                        <flux:select.option value="checking">Checking</flux:select.option>
                        <flux:select.option value="savings">Savings</flux:select.option>
                    </flux:select>
                    <flux:input type="number" step="0.01" wire:model="balance" label="Current Balance" placeholder="0.00" />
                </div>
                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">Save Account</flux:button>
                    <flux:button type="button" wire:click="$toggle('showForm')" variant="ghost">Cancel</flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</div>
