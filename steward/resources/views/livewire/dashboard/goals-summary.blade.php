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

<div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <x-lucide-target class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Goals</h2>
        </div>
        <a href="/goals" wire:navigate class="text-xs text-blue-500 hover:underline">View all</a>
    </div>

    @if ($goals->isEmpty())
        <div class="text-center py-4">
            <p class="text-sm text-zinc-400 dark:text-zinc-500">No goals set</p>
            <a href="/goals" wire:navigate class="text-xs text-blue-500 hover:underline mt-1 inline-block">Add a goal</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($goals as $goal)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-zinc-700 dark:text-zinc-300 font-medium truncate mr-2">{{ $goal->name }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400 flex-shrink-0">{{ $goal->progressPercent() }}%</span>
                    </div>
                    <div class="h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                        <div class="h-1.5 rounded-full bg-blue-500 transition-all"
                            style="width: {{ min(100, $goal->progressPercent()) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totalGoals > 3)
            <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-3">
                and {{ $totalGoals - 3 }} more
            </p>
        @endif
    @endif
</div>
