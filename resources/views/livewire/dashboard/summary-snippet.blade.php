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

<flux:card>
    <div class="flex items-center gap-2 mb-4">
        <flux:icon.sparkles variant="mini" class="text-zinc-400 dark:text-zinc-500" />
        <flux:heading size="sm">Today's Summary</flux:heading>
    </div>

    @if ($latestSummary)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:text size="sm">Total Spent</flux:text>
                <flux:heading size="lg">
                    ${{ number_format($latestSummary->total_spent, 2) }}
                </flux:heading>
            </div>

            @if ($latestSummary->ai_analysis)
                <flux:text size="sm" class="leading-relaxed">
                    {{ Str::limit($latestSummary->ai_analysis, 200) }}
                </flux:text>
            @endif

            <flux:text size="xs">
                {{ $latestSummary->period_start->format('M j, Y') }}
            </flux:text>
        </div>
    @else
        <div class="text-center py-4">
            <flux:icon.file-text class="size-8 text-zinc-300 dark:text-zinc-700 mx-auto mb-2" />
            <flux:text size="sm">No summaries yet</flux:text>
        </div>
    @endif
</flux:card>
