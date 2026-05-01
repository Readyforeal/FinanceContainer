<?php

use App\Models\Account;
use App\Models\Transaction;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Carbon;

new class extends Component {
    #[On('account-created')]
    #[On('transactions-imported')]
    public function refresh(): void
    {
        // Triggers re-render
    }

    public function with(): array
    {
        $accounts = Account::all();

        // Build sparkline chart data: last 30 days of daily spending per account
        $accountChartData = [];
        $today = Carbon::today();
        $start = $today->copy()->subDays(29);

        $dateRange = [];
        for ($i = 0; $i < 30; $i++) {
            $dateRange[] = $start->copy()->addDays($i)->toDateString();
        }

        foreach ($accounts as $account) {
            $dailyTotals = Transaction::where('account_id', $account->id)
                ->where('amount', '<', 0)
                ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
                ->selectRaw('date as day, ABS(SUM(amount)) as total')
                ->groupBy('day')
                ->pluck('total', 'day')
                ->toArray();

            $accountChartData[$account->id] = array_map(
                fn ($date) => round((float) ($dailyTotals[$date] ?? 0), 2),
                $dateRange
            );
        }

        return [
            'accounts' => $accounts,
            'accountChartData' => $accountChartData,
        ];
    }
};
?>

<div>
    @if ($accounts->isEmpty())
        <div class="text-center py-16">
            <x-lucide-landmark class="w-12 h-12 text-zinc-400 dark:text-zinc-600 mx-auto mb-4" />
            <h2 class="text-xl font-semibold text-zinc-700 dark:text-zinc-300 mb-2">No Accounts Yet</h2>
            <p class="text-zinc-400 dark:text-zinc-500 mb-6">Add your checking and savings accounts to get started.</p>
            <livewire:accounts.add-account />
        </div>
    @else
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Your Accounts</h2>
            <livewire:accounts.add-account />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach ($accounts as $account)
                <div class="bg-white dark:bg-zinc-800 rounded-xl p-5 border border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $account->name }}</p>
                            <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                                ${{ number_format($account->current_balance, 2) }}
                            </p>
                        </div>
                        <div class="p-2 bg-zinc-100 dark:bg-zinc-700 rounded-lg">
                            @if ($account->type->value === 'savings')
                                <x-lucide-piggy-bank class="w-5 h-5 text-indigo-500 dark:text-indigo-400" />
                            @else
                                <x-lucide-wallet class="w-5 h-5 text-blue-500 dark:text-blue-400" />
                            @endif
                        </div>
                    </div>

                    <div class="text-xs text-zinc-400 dark:text-zinc-500 space-y-1">
                        <div class="flex justify-between">
                            <span>Type</span>
                            <span class="capitalize text-zinc-500 dark:text-zinc-400">{{ $account->type->value }}</span>
                        </div>
                        @if ($account->last_synced_at)
                            <div class="flex justify-between">
                                <span>Last import</span>
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $account->last_synced_at->diffForHumans() }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Sparkline --}}
                    @if (array_sum($accountChartData[$account->id] ?? []) > 0)
                        <div wire:key="spark-{{ $account->id }}" x-data x-init="
                            new ApexCharts($refs['spark{{ $account->id }}'], {
                                chart: { type: 'area', height: 60, sparkline: { enabled: true } },
                                series: [{ data: @js($accountChartData[$account->id] ?? []) }],
                                stroke: { width: 2, curve: 'smooth' },
                                colors: ['{{ $account->type->value === 'checking' ? '#3b82f6' : '#6366f1' }}'],
                                fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
                                tooltip: { enabled: false },
                            }).render()
                        " class="mt-3">
                            <div x-ref="spark{{ $account->id }}"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- CSV Import --}}
        <livewire:accounts.csv-import />
    @endif
</div>
