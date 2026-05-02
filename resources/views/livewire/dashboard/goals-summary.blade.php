<?php

use App\Models\Goal;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];

        $allActiveGoals = Goal::where('is_completed', false)->get();

        $goals = $allActiveGoals
            ->sortBy(function (Goal $goal) use ($priorityOrder) {
                return $priorityOrder[$goal->priority] ?? 1;
            })
            ->take(3)
            ->values();

        $totalGoals = $allActiveGoals->count();

        $totalTarget = $allActiveGoals->sum(fn (Goal $g) => (float) $g->target_amount);
        $totalSaved = $allActiveGoals->sum(fn (Goal $g) => (float) $g->current_amount);
        $totalProgress = $totalTarget > 0
            ? round($totalSaved / $totalTarget * 100, 1)
            : 0.0;

        return compact('goals', 'totalGoals', 'totalProgress');
    }
};
?>

<flux:card>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon.target variant="mini" class="text-zinc-400 dark:text-zinc-500" />
            <flux:heading size="sm">Goals</flux:heading>
        </div>
        <flux:link href="/goals" wire:navigate size="sm">View all</flux:link>
    </div>

    @if ($goals->isEmpty())
        <div class="text-center py-4">
            <flux:text size="sm">No goals set</flux:text>
            <flux:link href="/goals" wire:navigate size="sm" class="mt-1 inline-block">Add a goal</flux:link>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($goals as $goal)
                <div>
                    <div class="flex justify-between mb-1">
                        <flux:text size="xs" class="font-medium truncate mr-2">{{ $goal->name }}</flux:text>
                        <flux:text size="xs" class="flex-shrink-0">{{ $goal->progressPercent() }}%</flux:text>
                    </div>
                    <div class="h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                        <div class="h-1.5 rounded-full bg-blue-500 transition-all"
                            style="width: {{ min(100, $goal->progressPercent()) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totalGoals > 3)
            <flux:text size="xs" class="mt-3">
                and {{ $totalGoals - 3 }} more
            </flux:text>
        @endif
    @endif
</flux:card>
