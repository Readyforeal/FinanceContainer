<?php

use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public int $days = 7;

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [7, 30]) ? $days : 7;
    }

    public function with(): array
    {
        $range = collect(range($this->days - 1, 0))->map(fn ($offset) => now()->subDays($offset)->startOfDay());

        $transactions = Transaction::whereBetween('date', [
            now()->subDays($this->days - 1)->startOfDay()->toDateString(),
            now()->toDateString(),
        ])->get()->groupBy(fn ($t) => $t->date->toDateString());

        $chartLabels = $range->map(fn ($day) => $day->format('M j'))->values()->toArray();
        $chartValues = $range->map(fn ($day) => (float) abs($transactions->get($day->toDateString())?->where('amount', '<', 0)->sum('amount') ?? 0))->values()->toArray();

        return [
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'days' => $this->days,
        ];
    }
};
?>

<flux:card>
    <div class="flex items-center justify-between mb-1">
        <flux:heading size="sm">Spending Trend</flux:heading>
        <div class="flex gap-1">
            <flux:button size="xs" :variant="$days === 7 ? 'primary' : 'subtle'" wire:click="setDays(7)">7d</flux:button>
            <flux:button size="xs" :variant="$days === 30 ? 'primary' : 'subtle'" wire:click="setDays(30)">30d</flux:button>
        </div>
    </div>

    <div wire:key="chart-{{ $days }}" x-data x-init="
        new ApexCharts($refs.chart, {
            chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
            series: [{ name: 'Spent', data: @js($chartValues) }],
            xaxis: { categories: @js($chartLabels) },
            colors: ['#3b82f6'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            grid: { borderColor: document.documentElement.classList.contains('dark') ? '#27272a' : '#e4e4e7' },
            theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
            dataLabels: { enabled: false },
        }).render()
    " class="mt-4">
        <div x-ref="chart"></div>
    </div>
</flux:card>
