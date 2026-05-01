<?php

use App\Enums\BudgetBucket;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public string $viewingMonth = '';
    public ?int $editCategoryId = null;
    public string $editAmount = '';
    public ?int $editingBudgetId = null;

    public function mount(): void
    {
        $this->viewingMonth = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->viewingMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->viewingMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth)->addMonth()->format('Y-m');
    }

    public function saveBudget(): void
    {
        $this->validate([
            'editAmount' => ['required', 'numeric', 'gt:0'],
        ]);

        if ($this->editingBudgetId) {
            Budget::findOrFail($this->editingBudgetId)->update([
                'budgeted_amount' => $this->editAmount,
            ]);
        } else {
            $category = Category::findOrFail($this->editCategoryId);

            Budget::create([
                'category_id' => $this->editCategoryId,
                'budgeted_amount' => $this->editAmount,
                'bucket' => $category->default_bucket,
            ]);
        }

        $this->cancelEdit();
    }

    public function editBudget(int $budgetId): void
    {
        $budget = Budget::findOrFail($budgetId);
        $this->editingBudgetId = $budget->id;
        $this->editCategoryId = $budget->category_id;
        $this->editAmount = (string) $budget->budgeted_amount;
    }

    public function deleteBudget(int $budgetId): void
    {
        Budget::findOrFail($budgetId)->delete();
    }

    public function cancelEdit(): void
    {
        $this->editCategoryId = null;
        $this->editAmount = '';
        $this->editingBudgetId = null;
    }

    public function with(): array
    {
        $monthDate = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth);

        $budgets = Budget::with('category')->get();

        $budgetedCategoryIds = $budgets->pluck('category_id')->toArray();

        foreach ($budgets as $budget) {
            $budget->spent = (float) abs(Transaction::where('category_id', $budget->category_id)
                ->whereYear('date', $monthDate->year)
                ->whereMonth('date', $monthDate->month)
                ->where('amount', '<', 0)
                ->sum('amount'));
        }

        $incomeSources = IncomeSource::where('is_active', true)->get();
        $totalMonthlyIncome = $incomeSources->sum(fn ($s) => $s->monthlyAmount());

        $totalBudgeted = $budgets->sum('budgeted_amount');

        $overIncome = $totalMonthlyIncome > 0 && $totalBudgeted > $totalMonthlyIncome;

        $bucketTotals = [];
        foreach (BudgetBucket::cases() as $bucket) {
            $bucketBudgets = $budgets->where('bucket', $bucket);
            $bucketTotals[$bucket->value] = [
                'budgeted' => $bucketBudgets->sum('budgeted_amount'),
                'spent' => $bucketBudgets->sum('spent'),
            ];
        }

        $availableCategories = Category::whereNotIn('id', $budgetedCategoryIds)->orderBy('name')->get();

        $ratios = AppSetting::getValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        return compact(
            'budgets',
            'totalMonthlyIncome',
            'totalBudgeted',
            'overIncome',
            'bucketTotals',
            'availableCategories',
            'ratios',
            'monthDate',
        );
    }
};
?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-3">
                <button
                    wire:click="previousMonth"
                    class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                    title="Previous month"
                >
                    <x-lucide-chevron-left class="w-5 h-5" />
                </button>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $monthDate->format('F Y') }} Budget
                </h1>
                <button
                    wire:click="nextMonth"
                    class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                    title="Next month"
                >
                    <x-lucide-chevron-right class="w-5 h-5" />
                </button>
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                ${{ number_format($totalBudgeted, 2) }} budgeted
                @if ($totalMonthlyIncome > 0)
                    of ${{ number_format($totalMonthlyIncome, 2) }} income
                @endif
            </p>
        </div>

        @if ($overIncome)
            <div class="flex items-center gap-2 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-2 text-red-700 dark:text-red-400 text-sm font-medium">
                <x-lucide-alert-triangle class="w-4 h-4 shrink-0" />
                <span>Budgets exceed income &mdash; over income by ${{ number_format($totalBudgeted - $totalMonthlyIncome, 2) }}</span>
            </div>
        @endif
    </div>

    {{-- Bucket sections --}}
    <div class="space-y-8">
        @foreach (App\Enums\BudgetBucket::cases() as $bucket)
            @php
                $bucketBudgets = $budgets->where('bucket', $bucket);
                $bucketData = $bucketTotals[$bucket->value];
                $target = $ratios[$bucket->value] ?? 0;
            @endphp

            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        @if ($bucket->value === 'needs')
                            <x-lucide-home class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                        @elseif ($bucket->value === 'wants')
                            <x-lucide-shopping-bag class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                        @else
                            <x-lucide-piggy-bank class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                        @endif
                        <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-200 capitalize">
                            {{ $bucket->value }}
                        </h2>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">({{ $target }}% target)</span>
                    </div>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        ${{ number_format($bucketData['budgeted'], 2) }} budgeted
                    </span>
                </div>

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
                    @forelse ($bucketBudgets as $budget)
                        @php
                            $remaining = $budget->budgeted_amount - $budget->spent;
                            $pct = $budget->budgeted_amount > 0 ? min(100, round($budget->spent / $budget->budgeted_amount * 100)) : 0;
                            $incomePct = $totalMonthlyIncome > 0 ? round($budget->budgeted_amount / $totalMonthlyIncome * 100) : 0;
                            $isOver = $budget->spent > $budget->budgeted_amount;
                        @endphp
                        <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0">
                            <div class="flex items-center gap-3 mb-2">
                                {{-- Icon + Name --}}
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <x-dynamic-component
                                        :component="'lucide-' . ($budget->category->icon ?? 'tag')"
                                        class="w-4 h-4 shrink-0 text-zinc-400 dark:text-zinc-500"
                                    />
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate">
                                        {{ $budget->category->name }}
                                    </span>
                                </div>

                                {{-- Amounts --}}
                                <div class="flex items-center gap-4 text-sm shrink-0">
                                    <span class="text-zinc-500 dark:text-zinc-400 hidden sm:block">
                                        {{ $incomePct }}% of income
                                    </span>
                                    <span class="text-zinc-700 dark:text-zinc-300 font-medium">
                                        ${{ number_format($budget->budgeted_amount, 2) }}
                                    </span>
                                    <span class="text-zinc-500 dark:text-zinc-400">
                                        spent ${{ number_format($budget->spent, 2) }}
                                    </span>
                                    <span class="{{ $isOver ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} font-medium">
                                        {{ $isOver ? '-' : '' }}${{ number_format(abs($remaining), 2) }} {{ $isOver ? 'over' : 'left' }}
                                    </span>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        wire:click="editBudget({{ $budget->id }})"
                                        class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                        title="Edit"
                                    >
                                        <x-lucide-pencil class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        wire:click="deleteBudget({{ $budget->id }})"
                                        wire:confirm="Delete this budget?"
                                        class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                        title="Delete"
                                    >
                                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>

                            {{-- Progress bar --}}
                            <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all {{ $isOver ? 'bg-red-500' : 'bg-emerald-500' }}"
                                    style="width: {{ $pct }}%"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-sm text-zinc-400 dark:text-zinc-500 text-center">
                            No {{ $bucket->value }} budgets yet.
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Add / Edit form --}}
    <div class="mt-8 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
        <h3 class="text-base font-semibold text-zinc-800 dark:text-zinc-200 mb-4">
            {{ $editingBudgetId ? 'Edit Budget' : 'Add Budget' }}
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Category select (locked when editing) --}}
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Category</label>
                @if ($editingBudgetId)
                    <div class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300">
                        {{ $budgets->firstWhere('id', $editingBudgetId)?->category->name ?? 'Selected' }}
                    </div>
                @else
                    <select
                        wire:model="editCategoryId"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-400"
                    >
                        <option value="">Select category...</option>
                        @foreach ($availableCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Amount</label>
                <input
                    wire:model="editAmount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    placeholder="0.00"
                    class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-400"
                />
                @error('editAmount')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Buttons --}}
            <div class="flex items-end gap-2">
                <button
                    wire:click="saveBudget"
                    class="flex-1 rounded-lg bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 px-4 py-2 text-sm font-medium hover:bg-zinc-700 dark:hover:bg-zinc-300 transition-colors"
                >
                    {{ $editingBudgetId ? 'Update' : 'Add Budget' }}
                </button>
                @if ($editingBudgetId)
                    <button
                        wire:click="cancelEdit"
                        class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors"
                    >
                        Cancel
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
