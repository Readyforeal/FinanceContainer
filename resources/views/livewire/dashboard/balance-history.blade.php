<?php

use App\Models\Account;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public int $days = 30;

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [7, 30, 90]) ? $days : 30;
    }

    public function with(): array
    {
        $accounts = Account::all();
        $currentBalance = (float) $accounts->sum('current_balance');

        // Build daily net change from transactions
        $startDate = now()
            ->subDays($this->days - 1)
            ->startOfDay();
        $dailyNets = Transaction::where('date', '>=', $startDate->toDateString())->selectRaw('date, SUM(amount) as net')->groupBy('date')->pluck('net', 'date')->mapWithKeys(fn($net, $date) => [\Carbon\Carbon::parse($date)->format('Y-m-d') => (float) $net]);

        // Work backwards from current balance to reconstruct history
        $dates = collect(range(0, $this->days - 1))
            ->map(fn($offset) => now()->subDays($offset)->startOfDay())
            ->reverse()
            ->values();

        // Calculate balance for each day by starting from current and going back
        $runningBalance = $currentBalance;

        // First, calculate the total of all transactions from today back to start
        // Then build forward from the calculated starting balance
        $futureDays = collect(range(0, $this->days - 1))->map(fn($offset) => now()->subDays($offset)->format('Y-m-d'));

        $totalNetSinceStart = 0;
        foreach ($futureDays as $dateStr) {
            $totalNetSinceStart += $dailyNets[$dateStr] ?? 0;
        }

        $startingBalance = $currentBalance - $totalNetSinceStart;

        // Now build forward
        $balances = [];
        $balance = $startingBalance;
        foreach ($dates as $date) {
            $dateStr = $date->format('Y-m-d');
            $balance += $dailyNets[$dateStr] ?? 0;
            $balances[] = round($balance, 2);
        }

        $chartLabels = $dates->map(fn($d) => $d->format('M j'))->toArray();
        $chartValues = $balances;

        // Calculate change over period
        $startVal = $balances[0] ?? 0;
        $endVal = end($balances) ?: 0;
        $change = $endVal - $startVal;

        return compact('chartLabels', 'chartValues', 'change');
    }
};
?>

<flux:card class="">
    <div class="flex items-center justify-between mb-1">
        <div class="flex items-center gap-2">
            <flux:icon.trending-up-down variant="mini" class="text-zinc-400 dark:text-zinc-500" />
            <flux:heading size="sm">Balance History</flux:heading>
        </div>
        <div class="flex items-center gap-2">
            @if ($change != 0)
                <flux:badge :color="$change >= 0 ? 'green' : 'red'" size="sm">
                    {{ $change >= 0 ? '+' : '' }}${{ number_format($change, 2) }}
                </flux:badge>
            @endif
            <div class="flex gap-1">
                <flux:button wire:click="setDays(7)" size="xs" :variant="$days === 7 ? 'primary' : 'subtle'">7d
                </flux:button>
                <flux:button wire:click="setDays(30)" size="xs" :variant="$days === 30 ? 'primary' : 'subtle'">30d
                </flux:button>
                <flux:button wire:click="setDays(90)" size="xs" :variant="$days === 90 ? 'primary' : 'subtle'">90d
                </flux:button>
            </div>
        </div>
    </div>

    <div class="pt-3 pr-3" wire:key="balance-chart-{{ $days }}" x-data x-init="
        const isDark = document.documentElement.classList.contains('dark');
        new ApexCharts($refs.chart, {
            chart: { type: 'area', height: 250, toolbar: { show: false }, background: 'transparent', parentHeightOffset: 0, fontFamily: 'Inter, sans-serif' },
            series: [{ name: 'Balance', data: @js($chartValues) }],
            xaxis: {
                categories: @js($chartLabels),
                labels: { style: { fontSize: '10px', colors: isDark ? '#71717a' : '#a1a1aa' } },
                tickAmount: Math.min(@js(count($chartLabels)), 6),
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: isDark ? '#71717a' : '#a1a1aa' }, formatter: (val) => '$' + val.toFixed(0) } },
            tooltip: { y: { formatter: (val) => '$' + val.toFixed(2) } },
            colors: ['#10b981'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 0.5, opacityFrom: 0.4, opacityTo: 0, stops: [0, 95] } },
            stroke: { curve: 'smooth', width: 2.5 },
            grid: { show: false, padding: { left: 10, right: 10, top: -10, bottom: 0 } },
            theme: { mode: isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
        }).render()
    " class="mt-1">
        <div x-ref="chart"></div>
    </div>
</flux:card>
