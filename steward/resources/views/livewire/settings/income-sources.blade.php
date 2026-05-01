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
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Income Sources</h2>
        @if ($sources->isNotEmpty())
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                Total Monthly:
                <span class="text-green-400 font-semibold ml-1">${{ number_format($totalMonthly, 2) }}</span>
            </div>
        @endif
    </div>

    {{-- Existing sources --}}
    @if ($sources->isNotEmpty())
        <div class="space-y-3 mb-6">
            @foreach ($sources as $source)
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-300 dark:border-zinc-700 p-4 flex items-center justify-between">
                    <div>
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $source->name }}</p>
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
                            <span class="text-sm text-red-400 mr-2">Confirm delete?</span>
                            <button
                                wire:click="delete({{ $source->id }})"
                                class="text-xs bg-red-700 hover:bg-red-600 text-white px-3 py-1 rounded"
                            >
                                Yes, Delete
                            </button>
                            <button
                                wire:click="cancelDelete"
                                class="text-xs bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-300 px-3 py-1 rounded"
                            >
                                Cancel
                            </button>
                        @else
                            <button
                                wire:click="edit({{ $source->id }})"
                                class="p-1.5 text-zinc-500 dark:text-zinc-400 hover:text-indigo-400 transition-colors"
                                title="Edit"
                            >
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                            <button
                                wire:click="confirmDelete({{ $source->id }})"
                                class="p-1.5 text-zinc-500 dark:text-zinc-400 hover:text-red-400 transition-colors"
                                title="Delete"
                            >
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Total summary --}}
        <div class="bg-zinc-100 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 mb-6 text-center">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Monthly Income</p>
            <p class="text-3xl font-bold text-green-400 mt-1">${{ number_format($totalMonthly, 2) }}</p>
        </div>
    @else
        <div class="text-center py-8 text-zinc-400 dark:text-zinc-500 mb-6">
            <x-lucide-wallet class="w-10 h-10 mx-auto mb-3 text-zinc-400 dark:text-zinc-600" />
            <p>No income sources added yet.</p>
        </div>
    @endif

    {{-- Add/Edit form --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-300 dark:border-zinc-700 p-5">
        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mb-4">
            {{ $editingId ? 'Edit Income Source' : 'Add Income Source' }}
        </h3>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Name</label>
                    <input
                        wire:model="name"
                        type="text"
                        placeholder="e.g. Main Job"
                        class="w-full bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-zinc-400 dark:placeholder-zinc-500"
                    />
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Amount</label>
                    <input
                        wire:model="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="w-full bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    @error('amount') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Frequency</label>
                    <select
                        wire:model="frequency"
                        class="w-full bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="weekly">Weekly</option>
                        <option value="biweekly">Biweekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    @error('frequency') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">Next Pay Date</label>
                    <input
                        wire:model="nextPayDate"
                        type="date"
                        class="w-full bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    @error('nextPayDate') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
                >
                    {{ $editingId ? 'Update Source' : 'Add Source' }}
                </button>

                @if ($editingId)
                    <button
                        type="button"
                        wire:click="cancelEdit"
                        class="bg-zinc-200 dark:bg-zinc-700 hover:bg-zinc-300 dark:hover:bg-zinc-600 text-zinc-700 dark:text-zinc-300 text-sm px-4 py-2 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
