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
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Summaries</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Your AI-generated financial summaries.</p>
    </div>

    {{-- Tab bar --}}
    <div class="flex items-center gap-1 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-1 w-fit mb-6">
        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $tab => $label)
            <button
                wire:click="setTab('{{ $tab }}')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                    {{ $activeTab === $tab
                        ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100'
                        : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
            >
                {{ $label }}
            </button>
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
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 mb-4">
            {{-- Period header --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <x-lucide-calendar class="w-4 h-4 text-zinc-400 dark:text-zinc-500 shrink-0" />
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                        @if ($summary->period_start->eq($summary->period_end))
                            {{ $summary->period_start->format('M j, Y') }}
                        @else
                            {{ $summary->period_start->format('M j') }} &ndash; {{ $summary->period_end->format('M j, Y') }}
                        @endif
                    </span>
                </div>
                <span class="text-xs text-zinc-400 dark:text-zinc-500 capitalize">{{ $summary->type }}</span>
            </div>

            {{-- Spending totals grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/50 p-3">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Total</p>
                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">${{ number_format($summary->total_spent, 2) }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3">
                    <p class="text-xs text-blue-500 dark:text-blue-400 mb-1">Needs</p>
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">${{ number_format($summary->needs_spent, 2) }}</p>
                </div>
                <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 p-3">
                    <p class="text-xs text-violet-500 dark:text-violet-400 mb-1">Wants</p>
                    <p class="text-sm font-semibold text-violet-700 dark:text-violet-300">${{ number_format($summary->wants_spent, 2) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-3">
                    <p class="text-xs text-emerald-500 dark:text-emerald-400 mb-1">Savings</p>
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">${{ number_format($summary->savings_spent, 2) }}</p>
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
                        <x-lucide-brain class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500" />
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Analysis</span>
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">{{ $summary->ai_analysis }}</p>
                </div>
            @endif

            {{-- AI advice --}}
            @if ($summary->ai_advice)
                <div class="mb-3">
                    <div class="flex items-center gap-1.5 mb-1">
                        <x-lucide-lightbulb class="w-3.5 h-3.5 text-amber-500" />
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Advice</span>
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">{{ $summary->ai_advice }}</p>
                </div>
            @endif

            {{-- Habit flags --}}
            @if (! empty($summary->habit_flags))
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($summary->habit_flags as $flag)
                        <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            {{ $flag }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-10 text-center">
            <x-lucide-file-text class="w-10 h-10 text-zinc-300 dark:text-zinc-600 mx-auto mb-3" />
            <p class="text-zinc-500 dark:text-zinc-400 font-medium">No summaries yet.</p>
            <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-1">They'll appear after your first daily sync.</p>
        </div>
    @endforelse

    {{-- Pagination --}}
    @if ($summaries->hasPages())
        <div class="mt-4">
            {{ $summaries->links() }}
        </div>
    @endif
</div>
