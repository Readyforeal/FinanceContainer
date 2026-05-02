<?php

use App\Enums\BudgetBucket;
use App\Models\AppSetting;
use App\Models\IncomeSource;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $expectedIncome = IncomeSource::where('is_active', true)->get()
            ->sum(fn ($s) => $s->monthlyAmount());

        // Actual income this month (income-bucketed transactions)
        $actualIncome = (float) Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->where('budget_bucket', 'income')
            ->where('amount', '>', 0)
            ->sum('amount');

        // Spending by bucket (only needs/wants/savings)
        $spendingBuckets = ['needs', 'wants', 'savings'];
        $bucketTotals = Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->whereIn('budget_bucket', $spendingBuckets)
            ->where('amount', '<', 0)
            ->get()
            ->groupBy(fn ($t) => $t->budget_bucket->value)
            ->map(fn ($group) => abs($group->sum('amount')));

        $needsSpent  = (float) ($bucketTotals['needs']   ?? 0);
        $wantsSpent  = (float) ($bucketTotals['wants']   ?? 0);
        $savingsSpent = (float) ($bucketTotals['savings'] ?? 0);
        $totalSpent  = $needsSpent + $wantsSpent + $savingsSpent;

        // Net savings (deposits minus withdrawals)
        $savingsWithdrawals = (float) abs(Transaction::whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->where('budget_bucket', 'transfer')
            ->where('amount', '>', 0)
            ->sum('amount'));
        $netSavings = $savingsSpent - $savingsWithdrawals;

        $ratios = AppSetting::getValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        // Use actual income as denominator if available, fall back to expected
        $denominator = $actualIncome > 0 ? $actualIncome : $expectedIncome;

        $needsPct    = $denominator > 0 ? round(($needsSpent / $denominator) * 100, 1) : 0;
        $wantsPct    = $denominator > 0 ? round(($wantsSpent / $denominator) * 100, 1) : 0;
        $savingsPct  = $denominator > 0 ? round(($savingsSpent / $denominator) * 100, 1) : 0;

        $buckets = [
            'needs'   => ['label' => 'Needs',   'spent' => $needsSpent,   'pct' => $needsPct,   'target' => $ratios['needs'],   'color' => 'bg-blue-500',   'over' => $needsPct   > $ratios['needs']],
            'wants'   => ['label' => 'Wants',   'spent' => $wantsSpent,   'pct' => $wantsPct,   'target' => $ratios['wants'],   'color' => 'bg-orange-500', 'over' => $wantsPct   > $ratios['wants']],
            'savings' => ['label' => 'Savings', 'spent' => $savingsSpent, 'pct' => $savingsPct, 'target' => $ratios['savings'], 'color' => 'bg-green-500',  'over' => $savingsPct > $ratios['savings']],
        ];

        return compact('buckets', 'totalSpent', 'expectedIncome', 'actualIncome', 'netSavings');
    }
};
?>

<div class="space-y-4">
    {{-- Income comparison --}}
    <flux:card>
        <div class="flex items-center justify-between mb-3">
            <flux:heading size="sm">{{ now()->format('F') }} Income</flux:heading>
            @if ($actualIncome > 0 && $expectedIncome > 0)
                @php $incomeVariance = $actualIncome - $expectedIncome; @endphp
                <flux:badge :color="$incomeVariance >= 0 ? 'green' : 'red'" size="sm">
                    {{ $incomeVariance >= 0 ? '+' : '' }}${{ number_format($incomeVariance, 2) }}
                </flux:badge>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <flux:text size="xs" class="mb-1">Expected</flux:text>
                <flux:heading size="lg">${{ number_format($expectedIncome, 2) }}</flux:heading>
            </div>
            <div>
                <flux:text size="xs" class="mb-1">Actual</flux:text>
                <flux:heading size="lg">${{ number_format($actualIncome, 2) }}</flux:heading>
            </div>
        </div>
    </flux:card>

    {{-- 50/30/20 Breakdown --}}
    <flux:card>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="sm">50 / 30 / 20 Breakdown</flux:heading>
            @if ($totalSpent > 0)
                <flux:text size="sm">${{ number_format($totalSpent, 2) }} spent</flux:text>
            @endif
        </div>

        {{-- Stacked bar --}}
        <div class="flex h-6 rounded-full overflow-hidden gap-0.5 bg-zinc-100 dark:bg-zinc-800 mb-4">
            @foreach ($buckets as $bucket)
                @if ($bucket['pct'] > 0)
                    <div class="{{ $bucket['color'] }} transition-all duration-500"
                         style="width: {{ min($bucket['pct'], 100) }}%"
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

        {{-- Net savings --}}
        @if ($netSavings != 0 || $buckets['savings']['spent'] > 0)
            <flux:separator class="my-4" />
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="font-medium">Net Savings</flux:text>
                <div class="text-right">
                    <flux:heading size="sm" class="{{ $netSavings >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $netSavings >= 0 ? '' : '-' }}${{ number_format(abs($netSavings), 2) }}
                    </flux:heading>
                    @if ($buckets['savings']['spent'] > 0 && $netSavings != $buckets['savings']['spent'])
                        <flux:text size="xs">${{ number_format($buckets['savings']['spent'], 2) }} deposited, ${{ number_format($buckets['savings']['spent'] - $netSavings, 2) }} withdrawn</flux:text>
                    @endif
                </div>
            </div>
        @endif
    </flux:card>
</div>
