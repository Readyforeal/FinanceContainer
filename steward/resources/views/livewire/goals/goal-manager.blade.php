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
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Active Goals</p>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $activeGoals->count() }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Total Targeted</p>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($totalTargeted, 2) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Total Saved</p>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($totalSaved, 2) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Overall Progress</p>
            <p class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                {{ $totalTargeted > 0 ? round(($totalSaved / $totalTargeted) * 100, 1) : 0 }}%
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Goals List --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Active Goals --}}
            @forelse ($activeGoals as $goal)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $goal->name }}</h3>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $goal->priority === 'high' ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : '' }}
                                    {{ $goal->priority === 'medium' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' : '' }}
                                    {{ $goal->priority === 'low' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : '' }}
                                ">
                                    {{ ucfirst($goal->priority) }}
                                </span>
                            </div>
                            @if ($goal->target_date)
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">Target: {{ $goal->target_date->format('M Y') }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                            <button wire:click="edit({{ $goal->id }})"
                                class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                            <button wire:click="toggleComplete({{ $goal->id }})"
                                class="p-1.5 rounded-lg text-zinc-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                <x-lucide-check class="w-4 h-4" />
                            </button>
                            <button wire:click="delete({{ $goal->id }})"
                                wire:confirm="Delete this goal?"
                                class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                <x-lucide-trash-2 class="w-4 h-4" />
                            </button>
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
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">Target</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($goal->target_amount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">Saved</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($goal->current_amount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">Remaining</p>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${{ number_format($goal->remaining(), 2) }}</p>
                        </div>
                    </div>

                    @if ($goal->target_date && $goal->monthlySavingsNeeded() !== null)
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-3">
                            Save ${{ number_format($goal->monthlySavingsNeeded(), 2) }}/mo to reach goal by {{ $goal->target_date->format('M Y') }}
                        </p>
                    @endif

                    @if ($goal->notes)
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-2 italic">{{ $goal->notes }}</p>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 text-center">
                    <x-lucide-target class="w-10 h-10 text-zinc-300 dark:text-zinc-700 mx-auto mb-3" />
                    <p class="text-zinc-500 dark:text-zinc-400 text-sm">No active goals yet</p>
                    <p class="text-zinc-400 dark:text-zinc-500 text-xs mt-1">Add a goal using the form</p>
                </div>
            @endforelse

            {{-- Completed Goals Toggle --}}
            @if ($completedGoals->isNotEmpty())
                <div>
                    <button wire:click="$toggle('showCompleted')"
                        class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 transition-colors">
                        <x-lucide-chevron-right class="w-4 h-4 transition-transform {{ $showCompleted ? 'rotate-90' : '' }}" />
                        {{ $showCompleted ? 'Hide' : 'Show' }} completed goals ({{ $completedGoals->count() }})
                    </button>

                    @if ($showCompleted)
                        <div class="mt-3 space-y-3">
                            @foreach ($completedGoals as $goal)
                                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50 p-4 opacity-75">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <x-lucide-check-circle class="w-4 h-4 text-green-500" />
                                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $goal->name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button wire:click="toggleComplete({{ $goal->id }})"
                                                class="p-1.5 rounded-lg text-zinc-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors text-xs">
                                                Reopen
                                            </button>
                                            <button wire:click="delete({{ $goal->id }})"
                                                wire:confirm="Delete this goal?"
                                                class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                                <x-lucide-trash-2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">
                                        ${{ number_format($goal->target_amount, 2) }} target
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

        </div>

        {{-- Add / Edit Form --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 h-fit">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                {{ $editingId ? 'Edit Goal' : 'Add Goal' }}
            </h2>

            <form wire:submit="save" class="space-y-4">

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Name</label>
                    <input wire:model="name" type="text" placeholder="e.g. Emergency Fund"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Target Amount ($)</label>
                    <input wire:model="targetAmount" type="number" step="0.01" min="0.01" placeholder="10000"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    @error('targetAmount') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Current Amount ($)</label>
                    <input wire:model="currentAmount" type="number" step="0.01" min="0" placeholder="0"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Target Date</label>
                    <input wire:model="targetDate" type="date"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Priority</label>
                    <select wire:model="priority"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Notes</label>
                    <textarea wire:model="notes" rows="3" placeholder="Optional notes..."
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        {{ $editingId ? 'Update Goal' : 'Add Goal' }}
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="cancelEdit"
                            class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                            Cancel
                        </button>
                    @endif
                </div>

            </form>
        </div>

    </div>
</div>
