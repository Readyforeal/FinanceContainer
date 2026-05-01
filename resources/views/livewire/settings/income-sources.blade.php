<?php

use App\Models\IncomeSource;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|numeric|min:0')]
    public string $amount = '';

    #[Validate('required|in:weekly,biweekly,monthly')]
    public string $frequency = 'monthly';

    #[Validate('nullable|date')]
    public string $nextPayDate = '';

    public ?int $editingId = null;
    public ?int $confirmDeleteId = null;

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'amount' => $this->amount,
            'frequency' => $this->frequency,
            'next_pay_date' => $this->nextPayDate ?: null,
            'is_active' => true,
        ];

        if ($this->editingId) {
            IncomeSource::findOrFail($this->editingId)->update($data);
        } else {
            IncomeSource::create($data);
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $source = IncomeSource::findOrFail($id);
        $this->editingId = $id;
        $this->name = $source->name;
        $this->amount = (string) $source->amount;
        $this->frequency = $source->frequency;
        $this->nextPayDate = $source->next_pay_date?->format('Y-m-d') ?? '';
    }

    public function delete(int $id): void
    {
        IncomeSource::findOrFail($id)->delete();
        $this->confirmDeleteId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->amount = '';
        $this->frequency = 'monthly';
        $this->nextPayDate = '';
        $this->resetValidation();
    }

    public function with(): array
    {
        $sources = IncomeSource::orderBy('name')->get();

        return [
            'sources' => $sources,
            'totalMonthly' => $sources->sum(fn ($s) => $s->monthlyAmount()),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">Income Sources</flux:heading>
        @if ($sources->isNotEmpty())
            <flux:text size="sm">
                Total Monthly:
                <span class="text-green-400 font-semibold ml-1">${{ number_format($totalMonthly, 2) }}</span>
            </flux:text>
        @endif
    </div>

    {{-- Existing sources --}}
    @if ($sources->isNotEmpty())
        <div class="space-y-3 mb-6">
            @foreach ($sources as $source)
                <flux:card class="p-4 flex items-center justify-between">
                    <div>
                        <flux:text class="font-medium">{{ $source->name }}</flux:text>
                        <div class="flex items-center gap-3 mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>${{ number_format($source->amount, 2) }} / {{ $source->frequency }}</span>
                            <span class="text-zinc-400 dark:text-zinc-600">&bull;</span>
                            <span class="text-green-400">${{ number_format($source->monthlyAmount(), 2) }}/mo</span>
                            @if ($source->next_pay_date)
                                <span class="text-zinc-400 dark:text-zinc-600">&bull;</span>
                                <span>Next: {{ $source->next_pay_date->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($confirmDeleteId === $source->id)
                            <flux:text size="sm" class="text-red-400 mr-2">Confirm delete?</flux:text>
                            <flux:button wire:click="delete({{ $source->id }})" variant="danger" size="sm">
                                Yes, Delete
                            </flux:button>
                            <flux:button wire:click="cancelDelete" variant="subtle" size="sm">
                                Cancel
                            </flux:button>
                        @else
                            <flux:button
                                wire:click="edit({{ $source->id }})"
                                variant="subtle"
                                size="xs"
                                icon="pencil"
                                title="Edit"
                            />
                            <flux:button
                                wire:click="confirmDelete({{ $source->id }})"
                                variant="subtle"
                                size="xs"
                                icon="trash-2"
                                title="Delete"
                            />
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Total summary --}}
        <flux:card class="p-4 mb-6 text-center">
            <flux:text size="sm">Total Monthly Income</flux:text>
            <flux:heading size="xl" class="text-green-400 mt-1">${{ number_format($totalMonthly, 2) }}</flux:heading>
        </flux:card>
    @else
        <div class="text-center py-8 mb-6">
            <flux:icon.wallet class="size-10 mx-auto mb-3 text-zinc-400 dark:text-zinc-600" />
            <flux:text class="text-zinc-400 dark:text-zinc-500">No income sources added yet.</flux:text>
        </div>
    @endif

    {{-- Add/Edit form --}}
    <flux:card class="p-5">
        <flux:heading size="sm" class="mb-4">
            {{ $editingId ? 'Edit Income Source' : 'Add Income Source' }}
        </flux:heading>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <flux:input
                        wire:model="name"
                        label="Name"
                        placeholder="e.g. Main Job"
                    />
                    @error('name') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="amount"
                        type="number"
                        label="Amount"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                    />
                    @error('amount') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:select wire:model="frequency" label="Frequency">
                        <flux:select.option value="weekly">Weekly</flux:select.option>
                        <flux:select.option value="biweekly">Biweekly</flux:select.option>
                        <flux:select.option value="monthly">Monthly</flux:select.option>
                    </flux:select>
                    @error('frequency') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>

                <div>
                    <flux:input
                        wire:model="nextPayDate"
                        type="date"
                        label="Next Pay Date"
                    />
                    @error('nextPayDate') <flux:text size="sm" class="text-red-500 mt-1">{{ $message }}</flux:text> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? 'Update Source' : 'Add Source' }}
                </flux:button>

                @if ($editingId)
                    <flux:button type="button" wire:click="cancelEdit" variant="subtle">
                        Cancel
                    </flux:button>
                @endif
            </div>
        </form>
    </flux:card>
</div>
