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
        $chartValues = $range->map(fn ($day) => (float) ($transactions->get($day->toDateString())?->sum('amount') ?? 0))->values()->toArray();

        return [
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'days' => $this->days,
        ];
    }
};
?>

<div class="bubble-assistant rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
    <div class="flex items-center justify-between mb-1">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Spending Trend</h2>
        <div class="flex gap-1">
            <button
                wire:click="setDays(7)"
                class="px-3 py-1 text-xs rounded-full transition-colors {{ $days === 7 ? 'bg-blue-500 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >7d</button>
            <button
                wire:click="setDays(30)"
                class="px-3 py-1 text-xs rounded-full transition-colors {{ $days === 30 ? 'bg-blue-500 text-white' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-200 dark:hover:bg-zinc-700' }}"
            >30d</button>
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
</div>
