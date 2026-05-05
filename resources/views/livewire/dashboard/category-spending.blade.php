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

        // Sort by spent descending
        $data = collect($data)->sortByDesc('spent')->values();

        $chartLabels = $data->pluck('label')->toArray();
        $chartSpent = $data->pluck('spent')->toArray();
        $chartBudget = $data->pluck('budget')->toArray();
        $monthLabel = $month->format('F');

        return compact('chartLabels', 'chartSpent', 'chartBudget', 'monthLabel');
    }
};
?>

<flux:card>
    <flux:heading size="sm" class="mb-1">{{ $monthLabel }} Spending vs Budget</flux:heading>

    @if (count($chartLabels) > 0)
        <div x-data x-init="
            const spent = @js($chartSpent);
            const budget = @js($chartBudget);
            const labels = @js($chartLabels);
            const isDark = document.documentElement.classList.contains('dark');

            // Tailwind 500 shades
            const barColors = [
                '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ec4899',
                '#06b6d4', '#f97316', '#6366f1', '#14b8a6', '#ef4444',
                '#84cc16', '#a855f7', '#0ea5e9', '#d946ef', '#22c55e',
                '#eab308', '#2dd4bf', '#f43f5e', '#818cf8', '#fb923c',
            ];

            const seriesData = spent.map((val, i) => ({
                x: labels[i],
                y: val,
                fillColor: barColors[i % barColors.length],
                goals: budget[i] > 0 ? [{
                    name: 'Budget',
                    value: budget[i],
                    strokeHeight: 2,
                    strokeColor: isDark ? '#fca5a5' : '#ef4444',
                    strokeDashArray: 3,
                }] : [],
            }));

            const minWidth = Math.max(labels.length * 60, 300);

            new ApexCharts($refs.chart, {
                chart: { type: 'bar', height: 300, width: minWidth, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
                series: [{ name: 'Spent', data: seriesData }],
                xaxis: {
                    labels: { style: { fontSize: '10px', colors: isDark ? '#71717a' : '#a1a1aa' }, rotate: -45, rotateAlways: labels.length > 6, trim: true, maxHeight: 80 },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: { show: false },
                tooltip: { y: { formatter: (val) => '$' + val.toFixed(2) } },
                plotOptions: { bar: { borderRadius: 6, columnWidth: '55%', distributed: true } },
                fill: { type: 'gradient', gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.2, opacityFrom: 1, opacityTo: 0.75, stops: [0, 100] } },
                legend: { show: false },
                grid: { show: false },
                theme: { mode: isDark ? 'dark' : 'light' },
                dataLabels: { enabled: false },
            }).render()
        " class="mt-2 overflow-x-auto">
            <div x-ref="chart"></div>
        </div>
    @else
        <div class="text-center py-8">
            <flux:text size="sm">No spending data yet this month.</flux:text>
        </div>
    @endif
</flux:card>
