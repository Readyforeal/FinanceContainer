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

<flux:card>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="sm">
            {{ now()->format('F') }} &mdash; 50 / 30 / 20 Breakdown
        </flux:heading>
        @if ($totalSpent > 0)
            <flux:text size="sm">${{ number_format($totalSpent, 2) }} spent</flux:text>
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
                <flux:text size="xs" class="font-medium mb-1">{{ $bucket['label'] }}</flux:text>
                <flux:heading size="lg">{{ $bucket['pct'] }}%</flux:heading>
                <flux:text size="xs">target {{ $bucket['target'] }}%</flux:text>
                <flux:text size="xs">${{ number_format($bucket['spent'], 2) }}</flux:text>
                @if ($bucket['over'])
                    <flux:text size="xs" class="text-red-500 font-medium mt-0.5">over target</flux:text>
                @endif
            </div>
        @endforeach
    </div>
</flux:card>
