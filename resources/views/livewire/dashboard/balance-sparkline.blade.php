<?php

use App\Models\Account;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $accounts = Account::all();
        $currentBalance = (float) $accounts->sum('current_balance');

        $days = 30;
        $startDate = now()->subDays($days - 1)->startOfDay();

        $dailyNets = Transaction::where('date', '>=', $startDate->toDateString())
            ->selectRaw('date, SUM(amount) as net')
            ->groupBy('date')
            ->pluck('net', 'date')
            ->mapWithKeys(fn ($net, $date) => [\Carbon\Carbon::parse($date)->format('Y-m-d') => (float) $net]);

        $dates = collect(range(0, $days - 1))
            ->map(fn ($offset) => now()->subDays($offset)->startOfDay())
            ->reverse()
            ->values();

        $futureDays = collect(range(0, $days - 1))
            ->map(fn ($offset) => now()->subDays($offset)->format('Y-m-d'));

        $totalNetSinceStart = 0;
        foreach ($futureDays as $dateStr) {
            $totalNetSinceStart += $dailyNets[$dateStr] ?? 0;
        }

        $startingBalance = $currentBalance - $totalNetSinceStart;

        $balances = [];
        $balance = $startingBalance;
        foreach ($dates as $date) {
            $dateStr = $date->format('Y-m-d');
            $balance += $dailyNets[$dateStr] ?? 0;
            $balances[] = round($balance, 2);
        }

        return [
            'sparkValues' => $balances,
            'currentBalance' => $currentBalance,
        ];
    }
};
?>

<div>
    <div class="flex items-end justify-between mb-1">
        <div>
            <flux:text size="xs" class="uppercase tracking-wide">Total Balance</flux:text>
            <flux:heading size="xl" class="!text-3xl">${{ number_format($currentBalance, 2) }}</flux:heading>
        </div>
    </div>
    <div x-data x-init="new ApexCharts($refs.spark, {
        chart: { type: 'area', height: 60, sparkline: { enabled: true }, background: 'transparent' },
        series: [{ data: @js($sparkValues) }],
        colors: ['#10b981'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
        stroke: { curve: 'smooth', width: 2 },
        tooltip: { enabled: false },
    }).render()">
        <div x-ref="spark"></div>
    </div>
</div>
