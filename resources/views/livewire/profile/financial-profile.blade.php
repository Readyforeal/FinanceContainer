<?php

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Goal;
use App\Models\IncomeSource;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        // Income
        $incomeSources = IncomeSource::where('is_active', true)->orderBy('name')->get();
        $totalMonthlyIncome = $incomeSources->sum(fn ($s) => $s->monthlyAmount());
        $annualIncome = $totalMonthlyIncome * 12;

        // Accounts
        $accounts = Account::orderBy('name')->get();
        $totalBalance = $accounts->sum(fn ($a) => (float) $a->current_balance);

        // Current month spending
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $currentMonthSpent = abs(Transaction::whereBetween('date', [$monthStart, $monthEnd])
            ->where('amount', '<', 0)
            ->sum('amount'));

        // Budget ratios from settings
        $budgetRatios = AppSetting::getValue('budget_ratios', [
            'needs' => 50,
            'wants' => 30,
            'savings' => 20,
        ]);

        // Bucket spending this month
        $bucketSpending = [
            'needs' => (float) abs(Transaction::whereBetween('date', [$monthStart, $monthEnd])
                ->where('budget_bucket', 'needs')
                ->where('amount', '<', 0)
                ->sum('amount')),
            'wants' => (float) abs(Transaction::whereBetween('date', [$monthStart, $monthEnd])
                ->where('budget_bucket', 'wants')
                ->where('amount', '<', 0)
                ->sum('amount')),
            'savings' => (float) abs(Transaction::whereBetween('date', [$monthStart, $monthEnd])
                ->where('budget_bucket', 'savings')
                ->where('amount', '<', 0)
                ->sum('amount')),
        ];

        // Goals
        $activeGoals = Goal::where('is_completed', false)
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'low' THEN 2 ELSE 3 END")
            ->get();
        $totalGoalTarget = $activeGoals->sum(fn ($g) => (float) $g->target_amount);
        $totalGoalSaved = $activeGoals->sum(fn ($g) => (float) $g->current_amount);

        // Monthly net position: income minus average monthly spending (last 3 months)
        $avgMonthlySpending = $this->averageMonthlySpending();
        $monthlyNetPosition = $totalMonthlyIncome - $avgMonthlySpending;
        $projectedAnnualSavings = $monthlyNetPosition * 12;

        return compact(
            'incomeSources',
            'totalMonthlyIncome',
            'annualIncome',
            'accounts',
            'totalBalance',
            'currentMonthSpent',
            'budgetRatios',
            'bucketSpending',
            'activeGoals',
            'totalGoalTarget',
            'totalGoalSaved',
            'monthlyNetPosition',
            'projectedAnnualSavings'
        );
    }

    private function averageMonthlySpending(): float
    {
        $totals = [];
        for ($i = 1; $i <= 3; $i++) {
            $start = now()->subMonths($i)->startOfMonth()->toDateString();
            $end = now()->subMonths($i)->endOfMonth()->toDateString();
            $totals[] = (float) abs(Transaction::whereBetween('date', [$start, $end])
                ->where('amount', '<', 0)
                ->sum('amount'));
        }

        $nonZero = array_filter($totals, fn ($m) => $m > 0);
        if (empty($nonZero)) {
            return 0.0;
        }

        return array_sum($nonZero) / count($nonZero);
    }
};
?>

<div class="space-y-6">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Card 1: Income & Accounts --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <div class="flex items-center gap-2 mb-4">
                <x-lucide-banknote class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Income &amp; Accounts</h2>
            </div>

            <div class="mb-4">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Income Sources</p>
                @forelse ($incomeSources as $source)
                    <div class="flex justify-between items-center py-1.5 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $source->name }}</span>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">${{ number_format($source->monthlyAmount(), 2) }}/mo</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-400 dark:text-zinc-500">No income sources configured</p>
                @endforelse

                <div class="flex justify-between items-center pt-2 mt-1">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Total Monthly</span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($totalMonthlyIncome, 2) }}</span>
                </div>
                <div class="flex justify-between items-center mt-0.5">
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">Annual Income</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">${{ number_format($annualIncome, 2) }}</span>
                </div>
            </div>

            <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Account Balances</p>
                @forelse ($accounts as $account)
                    <div class="flex justify-between items-center py-1.5 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $account->name }}</span>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">${{ number_format($account->current_balance, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-400 dark:text-zinc-500">No accounts connected</p>
                @endforelse

                @if ($accounts->isNotEmpty())
                    <div class="flex justify-between items-center pt-2 mt-1">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Total Balance</span>
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($totalBalance, 2) }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Card 2: Monthly Budget --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <div class="flex items-center gap-2 mb-4">
                <x-lucide-pie-chart class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Monthly Budget</h2>
            </div>

            @php
                $buckets = [
                    'needs' => ['label' => 'Needs', 'color' => 'bg-blue-500'],
                    'wants' => ['label' => 'Wants', 'color' => 'bg-amber-500'],
                    'savings' => ['label' => 'Savings', 'color' => 'bg-green-500'],
                ];
            @endphp

            <div class="space-y-3 mb-4">
                @foreach ($buckets as $key => $meta)
                    @php
                        $target = ($budgetRatios[$key] ?? 0) / 100 * $totalMonthlyIncome;
                        $actual = $bucketSpending[$key] ?? 0;
                        $pct = $target > 0 ? min(100, round($actual / $target * 100)) : 0;
                        $over = $actual > $target;
                    @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $meta['label'] }} ({{ $budgetRatios[$key] ?? 0 }}%)</span>
                            <span class="{{ $over ? 'text-red-500' : 'text-zinc-500 dark:text-zinc-400' }}">
                                ${{ number_format($actual, 2) }} / ${{ number_format($target, 2) }}
                            </span>
                        </div>
                        <div class="h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-1.5 rounded-full {{ $over ? 'bg-red-500' : $meta['color'] }} transition-all"
                                style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Monthly Net Position</span>
                    <span class="text-sm font-semibold {{ $monthlyNetPosition >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $monthlyNetPosition >= 0 ? '+' : '' }}${{ number_format(abs($monthlyNetPosition), 2) }}
                    </span>
                </div>
                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">
                    {{ $monthlyNetPosition >= 0 ? 'Surplus' : 'Deficit' }} vs average monthly spending
                </p>
            </div>
        </div>

        {{-- Card 3: Goals Progress --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <x-lucide-target class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Goals Progress</h2>
                </div>
                <a href="/goals" wire:navigate class="text-xs text-blue-500 hover:underline">View all</a>
            </div>

            @forelse ($activeGoals as $goal)
                <div class="mb-3 last:mb-0">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $goal->name }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $goal->progressPercent() }}%</span>
                    </div>
                    <div class="h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                        <div class="h-1.5 rounded-full bg-blue-500 transition-all"
                            style="width: {{ min(100, $goal->progressPercent()) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-zinc-400 dark:text-zinc-500">No active goals</p>
            @endforelse

            @if ($activeGoals->isNotEmpty())
                <div class="border-t border-zinc-100 dark:border-zinc-800 pt-3 mt-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-500 dark:text-zinc-400">Total Target</span>
                        <span class="text-zinc-700 dark:text-zinc-300">${{ number_format($totalGoalTarget, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs mt-0.5">
                        <span class="text-zinc-500 dark:text-zinc-400">Total Saved</span>
                        <span class="text-zinc-700 dark:text-zinc-300">${{ number_format($totalGoalSaved, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs mt-0.5">
                        <span class="text-zinc-500 dark:text-zinc-400">Overall</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $totalGoalTarget > 0 ? round($totalGoalSaved / $totalGoalTarget * 100, 1) : 0 }}%
                        </span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Card 4: Annual Outlook --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <div class="flex items-center gap-2 mb-4">
                <x-lucide-trending-up class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Annual Outlook</h2>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between items-center py-2 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Annual Income</span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($annualIncome, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Projected Annual Savings</span>
                    <span class="text-sm font-semibold {{ $projectedAnnualSavings >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $projectedAnnualSavings >= 0 ? '+' : '' }}${{ number_format(abs($projectedAnnualSavings), 2) }}
                    </span>
                </div>
            </div>

            @php
                $needsAnnual = ($budgetRatios['needs'] ?? 50) / 100 * $annualIncome;
                $wantsAnnual = ($budgetRatios['wants'] ?? 30) / 100 * $annualIncome;
                $savingsAnnual = ($budgetRatios['savings'] ?? 20) / 100 * $annualIncome;
            @endphp

            <div class="mt-4">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-3">Projected Annual Breakdown (50/30/20 target)</p>
                <div class="space-y-2">
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-600 dark:text-zinc-400">Needs ({{ $budgetRatios['needs'] ?? 50 }}%)</span>
                        <span class="text-zinc-700 dark:text-zinc-300">${{ number_format($needsAnnual, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-600 dark:text-zinc-400">Wants ({{ $budgetRatios['wants'] ?? 30 }}%)</span>
                        <span class="text-zinc-700 dark:text-zinc-300">${{ number_format($wantsAnnual, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-zinc-600 dark:text-zinc-400">Savings ({{ $budgetRatios['savings'] ?? 20 }}%)</span>
                        <span class="text-zinc-700 dark:text-zinc-300">${{ number_format($savingsAnnual, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    At this rate,
                    @if ($projectedAnnualSavings >= 0)
                        you are on track to save ${{ number_format($projectedAnnualSavings, 2) }} this year.
                    @else
                        you are spending ${{ number_format(abs($projectedAnnualSavings), 2) }} more per year than you earn.
                    @endif
                </p>
            </div>
        </div>

    </div>
</div>
