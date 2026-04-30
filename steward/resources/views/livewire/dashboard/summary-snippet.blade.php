<?php

use App\Models\Summary;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        return [
            'latestSummary' => Summary::where('type', 'daily')->latest('period_start')->first(),
        ];
    }
};
?>

<div class="bubble-assistant rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
    <div class="flex items-center gap-2 mb-4">
        <x-lucide-sparkles class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Today's Summary</h2>
    </div>

    @if ($latestSummary)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">Total Spent</span>
                <span class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    ${{ number_format($latestSummary->total_spent, 2) }}
                </span>
            </div>

            @if ($latestSummary->ai_analysis)
                <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    {{ Str::limit($latestSummary->ai_analysis, 200) }}
                </p>
            @endif

            <p class="text-xs text-zinc-400 dark:text-zinc-500">
                {{ $latestSummary->period_start->format('M j, Y') }}
            </p>
        </div>
    @else
        <div class="text-center py-4">
            <x-lucide-file-text class="w-8 h-8 text-zinc-300 dark:text-zinc-700 mx-auto mb-2" />
            <p class="text-sm text-zinc-400 dark:text-zinc-500">No summaries yet</p>
        </div>
    @endif
</div>
