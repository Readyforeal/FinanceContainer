<?php

use App\Enums\BudgetBucket;
use App\Models\AppSetting;
use App\Models\IncomeSource;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $totalIncome = IncomeSource::where('is_active', true)->get()
            ->sum(fn ($s) => $s->monthlyAmount());

        $bucketTotals = Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->whereNotNull('budget_bucket')
            ->where('amount', '<', 0)
            ->get()
            ->groupBy(fn ($t) => $t->budget_bucket->value)
            ->map(fn ($group) => abs($group->sum('amount')));

        $needsSpent  = (float) ($bucketTotals['needs']   ?? 0);
        $wantsSpent  = (float) ($bucketTotals['wants']   ?? 0);
        $savingsSpent = (float) ($bucketTotals['savings'] ?? 0);
        $totalSpent  = $needsSpent + $wantsSpent + $savingsSpent;

        $ratios = AppSetting::getValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        $needsPct    = $totalSpent > 0 ? round(($needsSpent / $totalSpent) * 100, 1) : 0;
        $wantsPct    = $totalSpent > 0 ? round(($wantsSpent / $totalSpent) * 100, 1) : 0;
        $savingsPct  = $totalSpent > 0 ? round(($savingsSpent / $totalSpent) * 100, 1) : 0;

        $buckets = [
            'needs'   => ['label' => 'Needs',   'spent' => $needsSpent,   'pct' => $needsPct,   'target' => $ratios['needs'],   'color' => 'bg-blue-500',   'over' => $needsPct   > $ratios['needs']],
            'wants'   => ['label' => 'Wants',   'spent' => $wantsSpent,   'pct' => $wantsPct,   'target' => $ratios['wants'],   'color' => 'bg-orange-500', 'over' => $wantsPct   > $ratios['wants']],
            'savings' => ['label' => 'Savings', 'spent' => $savingsSpent, 'pct' => $savingsPct, 'target' => $ratios['savings'], 'color' => 'bg-green-500',  'over' => $savingsPct > $ratios['savings']],
        ];

        return compact('buckets', 'totalSpent', 'totalIncome');
    }
};
?>

<div class="bubble-assistant rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
            {{ now()->format('F') }} &mdash; 50 / 30 / 20 Breakdown
        </h2>
        @if ($totalSpent > 0)
            <span class="text-xs text-zinc-400 dark:text-zinc-500">${{ number_format($totalSpent, 2) }} spent</span>
        @endif
    </div>

    {{-- Stacked bar --}}
    <div class="flex h-6 rounded-full overflow-hidden gap-0.5 bg-zinc-100 dark:bg-zinc-800 mb-4">
        @foreach ($buckets as $bucket)
            @if ($bucket['pct'] > 0)
                <div class="{{ $bucket['color'] }} transition-all duration-500"
                     style="width: {{ $bucket['pct'] }}%"
                     title="{{ $bucket['label'] }}: {{ $bucket['pct'] }}%">
                </div>
            @endif
        @endforeach
    </div>

    {{-- Stat columns --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach ($buckets as $bucket)
            <div class="text-center">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ $bucket['label'] }}</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $bucket['pct'] }}%</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">target {{ $bucket['target'] }}%</p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">${{ number_format($bucket['spent'], 2) }}</p>
                @if ($bucket['over'])
                    <p class="text-xs text-red-500 font-medium mt-0.5">over target</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
