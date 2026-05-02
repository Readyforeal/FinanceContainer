<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $month = now();

        // Get spending this month per category
        $spending = Transaction::whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->whereNotNull('category_id')
            ->whereIn('budget_bucket', ['needs', 'wants', 'savings'])
            ->where('amount', '<', 0)
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        // Fall back to last month if no current data
        if ($spending->isEmpty()) {
            $month = now()->subMonth();
            $spending = Transaction::whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->whereNotNull('category_id')
                ->whereIn('budget_bucket', ['needs', 'wants', 'savings'])
                ->where('amount', '<', 0)
                ->selectRaw('category_id, SUM(ABS(amount)) as total')
                ->groupBy('category_id')
                ->pluck('total', 'category_id');
        }

        $budgets = Budget::pluck('budgeted_amount', 'category_id');

        // Include any category with spending or a budget
        $categoryIds = $spending->keys()->merge($budgets->keys())->unique();
        $categories = Category::whereIn('id', $categoryIds)->pluck('name', 'id');

        $data = [];
        foreach ($categoryIds as $catId) {
            $spent = round((float) ($spending[$catId] ?? 0), 2);
            $budget = round((float) ($budgets[$catId] ?? 0), 2);
            if ($spent == 0 && $budget == 0) continue;

            $data[] = [
                'label' => $categories[$catId] ?? 'Unknown',
                'spent' => $spent,
                'budget' => $budget,
            ];
        }

        // Sort by spent descending, take top 12
        $data = collect($data)->sortByDesc('spent')->take(12)->values();

        $chartLabels = $data->pluck('label')->toArray();
        $chartSpent = $data->pluck('spent')->toArray();
        $chartBudget = $data->pluck('budget')->toArray();
        $monthLabel = $month->format('F');

        return compact('chartLabels', 'chartSpent', 'chartBudget', 'monthLabel');
    }
};
?>

<flux:card>
    <div class="flex items-center justify-between mb-1">
        <flux:heading size="sm">{{ $monthLabel }} Spending vs Budget</flux:heading>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span>
                <flux:text size="xs">Spent</flux:text>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-4 h-0.5 bg-red-400 rounded"></span>
                <flux:text size="xs">Budget</flux:text>
            </div>
        </div>
    </div>

    @if (count($chartLabels) > 0)
        <div x-data x-init="
            const spent = @js($chartSpent);
            const budget = @js($chartBudget);
            const labels = @js($chartLabels);
            const isDark = document.documentElement.classList.contains('dark');

            // Build data with per-bar goal lines
            const seriesData = spent.map((val, i) => ({
                x: labels[i],
                y: val,
                goals: budget[i] > 0 ? [{
                    name: 'Budget',
                    value: budget[i],
                    strokeHeight: 3,
                    strokeWidth: '100%',
                    strokeColor: '#f87171',
                    strokeDashArray: 0,
                    strokeLineCap: 'round',
                }] : [],
            }));

            new ApexCharts($refs.chart, {
                chart: { type: 'bar', height: 300, toolbar: { show: false }, background: 'transparent' },
                series: [{ name: 'Spent', data: seriesData }],
                xaxis: {
                    labels: { style: { fontSize: '10px' }, rotate: -45, rotateAlways: labels.length > 6, trim: true, maxHeight: 80 },
                },
                yaxis: { labels: { formatter: (val) => '$' + (val || 0).toFixed(0) } },
                tooltip: { y: { formatter: (val) => '$' + val.toFixed(2) } },
                colors: ['#3b82f6'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
                legend: {
                    show: true,
                    showForSingleSeries: true,
                    customLegendItems: ['Spent', 'Budget'],
                    markers: {
                        fillColors: ['#3b82f6', '#f87171'],
                        shape: ['square', 'line'],
                    },
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '11px',
                },
                grid: { borderColor: isDark ? '#27272a' : '#e4e4e7', strokeDashArray: 4 },
                theme: { mode: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
            }).render()
        " class="mt-2">
            <div x-ref="chart"></div>
        </div>
    @else
        <div class="text-center py-8">
            <flux:text size="sm">No spending data yet this month.</flux:text>
        </div>
    @endif
</flux:card>
