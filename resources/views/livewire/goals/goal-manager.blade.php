<?php

use App\Models\Goal;
use Livewire\Component;

new class extends Component {
    public string $name = '';
    public string $targetAmount = '';
    public string $currentAmount = '0';
    public string $targetDate = '';
    public string $priority = 'medium';
    public string $notes = '';
    public ?int $editingId = null;
    public bool $showCompleted = false;

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'targetAmount' => 'required|numeric|min:0.01',
        ]);

        if ($this->editingId) {
            $goal = Goal::findOrFail($this->editingId);
            $goal->update([
                'name' => $this->name,
                'target_amount' => $this->targetAmount,
                'current_amount' => $this->currentAmount ?: 0,
                'target_date' => $this->targetDate ?: null,
                'priority' => $this->priority,
                'notes' => $this->notes,
            ]);
        } else {
            Goal::create([
                'name' => $this->name,
                'target_amount' => $this->targetAmount,
                'current_amount' => $this->currentAmount ?: 0,
                'target_date' => $this->targetDate ?: null,
                'priority' => $this->priority,
                'notes' => $this->notes,
                'is_completed' => false,
            ]);
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $goal = Goal::findOrFail($id);
        $this->editingId = $id;
        $this->name = $goal->name;
        $this->targetAmount = (string) $goal->target_amount;
        $this->currentAmount = (string) $goal->current_amount;
        $this->targetDate = $goal->target_date ? $goal->target_date->format('Y-m-d') : '';
        $this->priority = $goal->priority;
        $this->notes = $goal->notes ?? '';
    }

    public function delete(int $id): void
    {
        Goal::findOrFail($id)->delete();
    }

    public function toggleComplete(int $id): void
    {
        $goal = Goal::findOrFail($id);
        $goal->update(['is_completed' => ! $goal->is_completed]);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->targetAmount = '';
        $this->currentAmount = '0';
        $this->targetDate = '';
        $this->priority = 'medium';
        $this->notes = '';
        $this->editingId = null;
    }

    public function with(): array
    {
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];

        $activeGoals = Goal::where('is_completed', false)
            ->get()
            ->sortBy(function (Goal $goal) use ($priorityOrder) {
                return [
                    $priorityOrder[$goal->priority] ?? 1,
                    $goal->target_date?->timestamp ?? PHP_INT_MAX,
                ];
            })
            ->values();

        $completedGoals = Goal::where('is_completed', true)
            ->orderByDesc('updated_at')
            ->get();

        $totalTargeted = $activeGoals->sum(fn (Goal $g) => (float) $g->target_amount);
        $totalSaved = $activeGoals->sum(fn (Goal $g) => (float) $g->current_amount);

        return compact('activeGoals', 'completedGoals', 'totalTargeted', 'totalSaved');
    }
};
?>

<div class="space-y-6">

    {{-- Summary Bar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <flux:card class="p-5">
            <flux:text size="sm" class="mb-1">Active Goals</flux:text>
            <flux:heading size="xl">{{ $activeGoals->count() }}</flux:heading>
        </flux:card>
        <flux:card class="p-5">
            <flux:text size="sm" class="mb-1">Total Targeted</flux:text>
            <flux:heading size="xl">${{ number_format($totalTargeted, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-5">
            <flux:text size="sm" class="mb-1">Total Saved</flux:text>
            <flux:heading size="xl">${{ number_format($totalSaved, 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-5">
            <flux:text size="sm" class="mb-1">Overall Progress</flux:text>
            <flux:heading size="xl">
                {{ $totalTargeted > 0 ? round(($totalSaved / $totalTargeted) * 100, 1) : 0 }}%
            </flux:heading>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Goals List --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Active Goals --}}
            @forelse ($activeGoals as $goal)
                <flux:card class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <flux:heading size="sm">{{ $goal->name }}</flux:heading>
                                <flux:badge :color="match($goal->priority) { 'high' => 'red', 'medium' => 'amber', 'low' => 'blue', default => 'zinc' }" size="sm">
                                    {{ ucfirst($goal->priority) }}
                                </flux:badge>
                            </div>
                            @if ($goal->target_date)
                                <flux:text size="sm" class="mt-0.5">Target: {{ $goal->target_date->format('M Y') }}</flux:text>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                            <flux:button wire:click="edit({{ $goal->id }})" variant="ghost" size="sm" icon="pencil" />
                            <flux:button wire:click="toggleComplete({{ $goal->id }})" variant="ghost" size="sm" icon="check" class="hover:text-green-600 dark:hover:text-green-400" />
                            <flux:button wire:click="delete({{ $goal->id }})" wire:confirm="Delete this goal?" variant="ghost" size="sm" icon="trash-2" class="hover:text-red-600 dark:hover:text-red-400" />
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    <div class="mb-3">
                        <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                            <span>${{ number_format($goal->current_amount, 2) }} saved</span>
                            <span>{{ $goal->progressPercent() }}%</span>
                        </div>
                        <div class="h-2 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full bg-blue-500 transition-all"
                                style="width: {{ min(100, $goal->progressPercent()) }}%"></div>
                        </div>
                    </div>

                    {{-- Amounts row --}}
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <flux:text size="sm">Target</flux:text>
                            <flux:text class="font-semibold">${{ number_format($goal->target_amount, 2) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm">Saved</flux:text>
                            <flux:text class="font-semibold">${{ number_format($goal->current_amount, 2) }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm">Remaining</flux:text>
                            <flux:text class="font-semibold">${{ number_format($goal->remaining(), 2) }}</flux:text>
                        </div>
                    </div>

                    @if ($goal->target_date && $goal->monthlySavingsNeeded() !== null)
                        <flux:text size="sm" class="mt-3">
                            Save ${{ number_format($goal->monthlySavingsNeeded(), 2) }}/mo to reach goal by {{ $goal->target_date->format('M Y') }}
                        </flux:text>
                    @endif

                    @if ($goal->notes)
                        <flux:text size="sm" class="mt-2 italic">{{ $goal->notes }}</flux:text>
                    @endif
                </flux:card>
            @empty
                <flux:card class="p-5 text-center">
                    <flux:icon.target class="size-10 text-zinc-300 dark:text-zinc-700 mx-auto mb-3" />
                    <flux:text>No active goals yet</flux:text>
                    <flux:text size="sm" class="mt-1">Add a goal using the form</flux:text>
                </flux:card>
            @endforelse

            {{-- Completed Goals Toggle --}}
            @if ($completedGoals->isNotEmpty())
                <div>
                    <button wire:click="$toggle('showCompleted')"
                        class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors">
                        <flux:icon.chevron-right variant="mini" class="transition-transform {{ $showCompleted ? 'rotate-90' : '' }}" />
                        {{ $showCompleted ? 'Hide' : 'Show' }} completed goals ({{ $completedGoals->count() }})
                    </button>

                    @if ($showCompleted)
                        <div class="mt-3 space-y-3">
                            @foreach ($completedGoals as $goal)
                                <flux:card class="p-4 opacity-75">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.circle-check variant="mini" class="text-green-500" />
                                            <flux:text class="font-medium">{{ $goal->name }}</flux:text>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <flux:button wire:click="toggleComplete({{ $goal->id }})" variant="ghost" size="sm">
                                                Reopen
                                            </flux:button>
                                            <flux:button wire:click="delete({{ $goal->id }})" wire:confirm="Delete this goal?" variant="ghost" size="sm" icon="trash-2" class="hover:text-red-600 dark:hover:text-red-400" />
                                        </div>
                                    </div>
                                    <flux:text size="sm" class="mt-1">
                                        ${{ number_format($goal->target_amount, 2) }} target
                                    </flux:text>
                                </flux:card>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

        </div>

        {{-- Add / Edit Form --}}
        <flux:card class="p-5 h-fit">
            <flux:heading size="sm" class="mb-4">
                {{ $editingId ? 'Edit Goal' : 'Add Goal' }}
            </flux:heading>

            <form wire:submit="save" class="space-y-4">

                <flux:input wire:model="name" label="Name" placeholder="e.g. Emergency Fund" />
                @error('name') <flux:text class="text-red-500 text-xs">{{ $message }}</flux:text> @enderror

                <flux:input wire:model="targetAmount" label="Target Amount ($)" type="number" step="0.01" min="0.01" placeholder="10000" />
                @error('targetAmount') <flux:text class="text-red-500 text-xs">{{ $message }}</flux:text> @enderror

                <flux:input wire:model="currentAmount" label="Current Amount ($)" type="number" step="0.01" min="0" placeholder="0" />

                <flux:input wire:model="targetDate" label="Target Date" type="date" />

                <flux:select wire:model="priority" label="Priority">
                    <flux:select.option value="high">High</flux:select.option>
                    <flux:select.option value="medium">Medium</flux:select.option>
                    <flux:select.option value="low">Low</flux:select.option>
                </flux:select>

                <flux:textarea wire:model="notes" label="Notes" rows="3" placeholder="Optional notes..." />

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" class="flex-1">
                        {{ $editingId ? 'Update Goal' : 'Add Goal' }}
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
</div>
