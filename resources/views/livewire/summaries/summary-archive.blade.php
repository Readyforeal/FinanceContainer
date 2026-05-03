<?php

use App\Models\Summary;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $activeTab = 'daily';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function with(): array
    {
        $summaries = Summary::where('type', $this->activeTab)
            ->orderByDesc('period_start')
            ->paginate(10);

        return compact('summaries');
    }
};
?>

<div>
    {{-- Header --}}
    <div class="mb-6">
        <flux:heading size="xl" level="1" class="!text-3xl">Summaries</flux:heading>
        <flux:text size="sm" class="mt-1">Your AI-generated financial summaries.</flux:text>
    </div>

    {{-- Tab bar --}}
    <div class="flex items-center gap-1 mb-6">
        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $tab => $label)
            <flux:button
                wire:click="setTab('{{ $tab }}')"
                :variant="$activeTab === $tab ? 'subtle' : 'ghost'"
                size="sm"
            >
                {{ $label }}
            </flux:button>
        @endforeach
    </div>

    {{-- Summary cards --}}
    @forelse ($summaries as $summary)
        @php
            $total = (float) $summary->total_spent;
            $needsPct  = $total > 0 ? round($summary->needs_spent  / $total * 100) : 0;
            $wantsPct  = $total > 0 ? round($summary->wants_spent  / $total * 100) : 0;
            $savingsPct = $total > 0 ? round($summary->savings_spent / $total * 100) : 0;
        @endphp
        <flux:card class="p-5 mb-4">
            {{-- Period header --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <flux:icon.calendar variant="mini" class="text-zinc-400 dark:text-zinc-500" />
                    <flux:text class="font-semibold">
                        @if ($summary->period_start->eq($summary->period_end))
                            {{ $summary->period_start->format('M j, Y') }}
                        @else
                            {{ $summary->period_start->format('M j') }} &ndash; {{ $summary->period_end->format('M j, Y') }}
                        @endif
                    </flux:text>
                </div>
                <flux:badge size="sm" color="zinc">{{ ucfirst($summary->type) }}</flux:badge>
            </div>

            {{-- Spending totals grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-3">
                    <flux:text size="sm" class="mb-1">Total</flux:text>
                    <flux:text class="font-semibold">${{ number_format($summary->total_spent, 2) }}</flux:text>
                </div>
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3">
                    <flux:text size="sm" class="text-blue-500 dark:text-blue-400 mb-1">Needs</flux:text>
                    <flux:text class="font-semibold text-blue-700 dark:text-blue-300">${{ number_format($summary->needs_spent, 2) }}</flux:text>
                </div>
                <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 p-3">
                    <flux:text size="sm" class="text-violet-500 dark:text-violet-400 mb-1">Wants</flux:text>
                    <flux:text class="font-semibold text-violet-700 dark:text-violet-300">${{ number_format($summary->wants_spent, 2) }}</flux:text>
                </div>
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-3">
                    <flux:text size="sm" class="text-emerald-500 dark:text-emerald-400 mb-1">Savings</flux:text>
                    <flux:text class="font-semibold text-emerald-700 dark:text-emerald-300">${{ number_format($summary->savings_spent, 2) }}</flux:text>
                </div>
            </div>

            {{-- 50/30/20 visual bar --}}
            @if ($total > 0)
                <div class="mb-4">
                    <div class="flex h-2 rounded-full overflow-hidden gap-px bg-zinc-100 dark:bg-zinc-800">
                        <div class="bg-blue-500 transition-all" style="width: {{ $needsPct }}%"></div>
                        <div class="bg-violet-500 transition-all" style="width: {{ $wantsPct }}%"></div>
                        <div class="bg-emerald-500 transition-all" style="width: {{ $savingsPct }}%"></div>
                    </div>
                    <div class="flex justify-between mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                        <span>Needs {{ $needsPct }}%</span>
                        <span>Wants {{ $wantsPct }}%</span>
                        <span>Savings {{ $savingsPct }}%</span>
                    </div>
                </div>
            @endif

            {{-- AI analysis --}}
            @if ($summary->ai_analysis)
                <div class="mb-3">
                    <div class="flex items-center gap-1.5 mb-1">
                        <flux:icon.brain variant="mini" class="text-zinc-400 dark:text-zinc-500" />
                        <flux:text size="sm" class="font-medium uppercase tracking-wide">Analysis</flux:text>
                    </div>
                    <flux:text class="leading-relaxed">{{ $summary->ai_analysis }}</flux:text>
                </div>
            @endif

            {{-- AI advice --}}
            @if ($summary->ai_advice)
                <div class="mb-3">
                    <div class="flex items-center gap-1.5 mb-1">
                        <flux:icon.lightbulb variant="mini" class="text-amber-500" />
                        <flux:text size="sm" class="font-medium uppercase tracking-wide">Advice</flux:text>
                    </div>
                    <flux:text class="leading-relaxed">{{ $summary->ai_advice }}</flux:text>
                </div>
            @endif

            {{-- Habit flags --}}
            @if (! empty($summary->habit_flags))
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($summary->habit_flags as $flag)
                        <flux:badge size="sm" color="zinc">{{ $flag }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @empty
        <flux:card class="p-10 text-center">
            <flux:icon.file-text class="size-10 text-zinc-300 dark:text-zinc-600 mx-auto mb-3" />
            <flux:text class="font-medium">No summaries yet.</flux:text>
            <flux:text size="sm" class="mt-1">They'll appear after your first daily sync.</flux:text>
        </flux:card>
    @endforelse

    {{-- Pagination --}}
    @if ($summaries->hasPages())
        <div class="mt-4">
            {{ $summaries->links() }}
        </div>
    @endif
</div>
