<?php

use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $uncategorized = Transaction::whereNull('category_id')->count();
        $needsReview = Transaction::where('needs_review', true)->count();
        $pendingJobs = (int) \Illuminate\Support\Facades\Redis::llen('queues:ai');

        return compact('uncategorized', 'needsReview', 'pendingJobs');
    }
};
?>

@if ($uncategorized > 0 || $pendingJobs > 0)
    <div wire:poll.5s class="flex items-center gap-3 px-4 py-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/20">
        <div class="flex items-center gap-2">
            @if ($pendingJobs > 0)
                <span class="relative flex size-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full size-3 bg-amber-500"></span>
                </span>
                <flux:text size="sm" class="font-medium">
                    AI is categorizing {{ $uncategorized }} transaction{{ $uncategorized !== 1 ? 's' : '' }}...
                </flux:text>
            @else
                <flux:icon.circle-alert variant="mini" class="text-amber-500" />
                <flux:text size="sm" class="font-medium">
                    {{ $uncategorized }} uncategorized transaction{{ $uncategorized !== 1 ? 's' : '' }} awaiting review
                </flux:text>
            @endif
        </div>
    </div>
@endif
