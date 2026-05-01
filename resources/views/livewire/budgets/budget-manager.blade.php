<?php

use App\Enums\BudgetBucket;
use App\Models\AppSetting;
use App\Models\Budget;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Models\Transaction;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $viewingMonth = '';
    public ?int $editCategoryId = null;
    public string $editAmount = '';
    public ?int $editingBudgetId = null;

    public function mount(): void
    {
        $this->viewingMonth = now()->format('Y-m');
        $this->js("setTimeout(() => window.dispatchEvent(new CustomEvent('set-dock-action', { detail: { icon: 'plus' } })), 100)");
    }

    public function previousMonth(): void
    {
        $this->viewingMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->viewingMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->viewingMonth)->addMonth()->format('Y-m');
    }

    #[On('dock-action')]
    public function openCreateModal(): void
    {
        $this->editingBudgetId = null;
        $this->editCategoryId = null;
        $this->editAmount = '';
        $this->modal('budget-editor')->show();
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

        $this->modal('budget-editor')->close();
        $this->editCategoryId = null;
        $this->editAmount = '';
        $this->editingBudgetId = null;
    }

    public function editBudget(int $budgetId): void
    {
        $budget = Budget::findOrFail($budgetId);
        $this->editingBudgetId = $budget->id;
        $this->editCategoryId = $budget->category_id;
        $this->editAmount = (string) $budget->budgeted_amount;
        $this->modal('budget-editor')->show();
    }

    public function deleteBudget(int $budgetId): void
    {
        Budget::findOrFail($budgetId)->delete();
    }

    public function cancelEdit(): void
    {
        $this->modal('budget-editor')->close();
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
            $budget->spent = (float) abs(Transaction::where('category_id', $budget->category_id)->whereYear('date', $monthDate->year)->whereMonth('date', $monthDate->month)->where('amount', '<', 0)->sum('amount'));
        }

        $incomeSources = IncomeSource::where('is_active', true)->get();
        $totalMonthlyIncome = $incomeSources->sum(fn($s) => $s->monthlyAmount());

        $totalBudgeted = $budgets->sum('budgeted_amount');

        $overIncome = $totalMonthlyIncome > 0 && $totalBudgeted > $totalMonthlyIncome;

        $spendingBuckets = array_filter(BudgetBucket::cases(), fn($b) => $b->isSpending());

        // Actual income for the viewed month
        $actualIncome = (float) Transaction::whereYear('date', $monthDate->year)->whereMonth('date', $monthDate->month)->where('budget_bucket', 'income')->where('amount', '>', 0)->sum('amount');

        $incomeBase = $actualIncome > 0 ? $actualIncome : $totalMonthlyIncome;

        $bucketTotals = [];
        foreach ($spendingBuckets as $bucket) {
            $bucketBudgets = $budgets->where('bucket', $bucket);
            $spent = $bucketBudgets->sum('spent');
            $bucketTotals[$bucket->value] = [
                'budgeted' => $bucketBudgets->sum('budgeted_amount'),
                'spent' => $spent,
                'actualPct' => $incomeBase > 0 ? round(($spent / $incomeBase) * 100, 1) : 0,
            ];
        }

        $availableCategories = Category::whereNotIn('id', $budgetedCategoryIds)->orderBy('name')->get();

        $ratios = AppSetting::getValue('budget_ratios', ['needs' => 50, 'wants' => 30, 'savings' => 20]);

        return compact('budgets', 'totalMonthlyIncome', 'incomeBase', 'totalBudgeted', 'overIncome', 'bucketTotals', 'availableCategories', 'ratios', 'monthDate', 'spendingBuckets');
    }
};
?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-3 justify-between md:justify-start">
                <flux:button variant="subtle" square icon="chevron-left" wire:click="previousMonth" />
                <flux:heading size="xl">{{ $monthDate->format('F Y') }} Budget</flux:heading>
                <flux:button variant="subtle" square icon="chevron-right" wire:click="nextMonth" />
            </div>
            <flux:text size="sm" class="mt-1 text-center md:text-left">
                ${{ number_format($totalBudgeted, 2) }} budgeted
                @if ($totalMonthlyIncome > 0)
                    of ${{ number_format($totalMonthlyIncome, 2) }} income
                @endif
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            @if ($overIncome)
                <flux:callout variant="danger" icon="triangle-alert" class="!py-2">
                    Budgets exceed income &mdash; over by ${{ number_format($totalBudgeted - $totalMonthlyIncome, 2) }}
                </flux:callout>
            @endif
            <div class="hidden lg:block">
                <flux:button wire:click="openCreateModal" variant="primary" icon="plus">Add Budget</flux:button>
            </div>
        </div>
    </div>

    {{-- Bucket sections --}}
    <div class="space-y-8">
        @foreach ($spendingBuckets as $bucket)
            @php
                $bucketBudgets = $budgets->where('bucket', $bucket);
                $bucketData = $bucketTotals[$bucket->value];
                $target = $ratios[$bucket->value] ?? 0;
            @endphp

            <div>
                {{-- Bucket header --}}
                @php
                    $budgetedPct = $incomeBase > 0 ? round(($bucketData['budgeted'] / $incomeBase) * 100, 1) : 0;
                @endphp
                <div class="mb-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if ($bucket->value === 'needs')
                                <flux:icon.house variant="mini" class="text-zinc-500 dark:text-zinc-400" />
                            @elseif ($bucket->value === 'wants')
                                <flux:icon.shopping-bag variant="mini" class="text-zinc-500 dark:text-zinc-400" />
                            @else
                                <flux:icon.piggy-bank variant="mini" class="text-zinc-500 dark:text-zinc-400" />
                            @endif
                            <flux:heading size="sm" class="capitalize">{{ $bucket->value }}</flux:heading>
                            <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 hidden sm:inline">
                                ({{ $target }}% target)
                            </flux:text>
                        </div>
                        <flux:text size="sm" class="font-medium shrink-0">
                            ${{ number_format($bucketData['budgeted'], 2) }}
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2 mt-1 justify-between md:justify-start">
                        <flux:text size="xs" class="text-zinc-400 dark:text-zinc-500 sm:hidden">
                            {{ $target }}% target</flux:text>
                        @if ($budgetedPct > 0)
                            <flux:badge :color="$budgetedPct > $target ? 'red' : 'zinc'" size="sm">
                                {{ $budgetedPct }}% budgeted
                            </flux:badge>
                        @endif
                        @if ($bucketData['actualPct'] > 0)
                            <flux:badge :color="$bucketData['actualPct'] > $target ? 'red' : 'green'" size="sm">
                                {{ $bucketData['actualPct'] }}% spent
                            </flux:badge>
                        @endif
                    </div>
                </div>

                <flux:card class="!p-0 overflow-hidden">
                    @forelse ($bucketBudgets as $budget)
                        @php
                            $remaining = $budget->budgeted_amount - $budget->spent;
                            $pct =
                                $budget->budgeted_amount > 0
                                    ? min(100, round(($budget->spent / $budget->budgeted_amount) * 100))
                                    : 0;
                            $incomePct =
                                $totalMonthlyIncome > 0
                                    ? round(($budget->budgeted_amount / $totalMonthlyIncome) * 100)
                                    : 0;
                            $isOver = $budget->spent > $budget->budgeted_amount;
                        @endphp
                        <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0">
                            {{-- Desktop row --}}
                            <div class="hidden lg:flex items-center gap-3 mb-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <flux:icon :icon="$budget->category->icon ?? 'tag'" variant="mini"
                                        class="shrink-0 text-zinc-400 dark:text-zinc-500" />
                                    <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate">
                                        {{ $budget->category->name }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 shrink-0">
                                    <flux:text size="sm">{{ $incomePct }}% of income</flux:text>
                                    <flux:text size="sm" class="font-medium">
                                        ${{ number_format($budget->budgeted_amount, 2) }}
                                    </flux:text>
                                    <flux:text size="sm">spent ${{ number_format($budget->spent, 2) }}</flux:text>
                                    <span
                                        class="{{ $isOver ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} text-sm font-medium">
                                        {{ $isOver ? '-' : '' }}${{ number_format(abs($remaining), 2) }}
                                        {{ $isOver ? 'over' : 'left' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <flux:button variant="subtle" size="xs" icon="pencil"
                                        wire:click="editBudget({{ $budget->id }})" />
                                    <flux:button variant="subtle" size="xs" icon="trash-2"
                                        wire:click="deleteBudget({{ $budget->id }})"
                                        wire:confirm="Delete this budget?" />
                                </div>
                            </div>

                            {{-- Mobile row --}}
                            <div class="lg:hidden mb-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <flux:icon :icon="$budget->category->icon ?? 'tag'" variant="mini"
                                            class="shrink-0 text-zinc-400 dark:text-zinc-500" />
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $budget->category->name }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span
                                            class="{{ $isOver ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} text-sm font-semibold">
                                            {{ $isOver ? '-' : '' }}${{ number_format(abs($remaining), 2) }}
                                            {{ $isOver ? 'over' : 'left' }}
                                        </span>
                                        <flux:button variant="subtle" size="xs" icon="pencil"
                                            wire:click="editBudget({{ $budget->id }})" />
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 mt-1 ml-7">
                                    <flux:text size="xs">${{ number_format($budget->budgeted_amount, 2) }}
                                        budgeted</flux:text>
                                    <flux:text size="xs">spent ${{ number_format($budget->spent, 2) }}</flux:text>
                                </div>
                            </div>

                            {{-- Progress bar --}}
                            <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                <div class="h-full rounded-full transition-all {{ $isOver ? 'bg-red-500' : 'bg-emerald-500' }}"
                                    style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center">
                            <flux:text size="sm">No {{ $bucket->value }} budgets yet.</flux:text>
                        </div>
                    @endforelse
                </flux:card>
            </div>
        @endforeach
    </div>

    {{-- Budget editor modal --}}
    <flux:modal name="budget-editor" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingBudgetId ? 'Edit Budget' : 'Add Budget' }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $editingBudgetId ? 'Update the budgeted amount.' : 'Set a monthly budget for a category.' }}
                </flux:text>
            </div>

            @if ($editingBudgetId)
                <flux:input label="Category"
                    value="{{ $budgets->firstWhere('id', $editingBudgetId)?->category->name ?? 'Selected' }}"
                    readonly />
            @else
                <flux:select wire:model="editCategoryId" label="Category" placeholder="Select category...">
                    @foreach ($availableCategories as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="editAmount" type="number" label="Monthly Amount" min="0.01" step="0.01"
                placeholder="0.00" />
            @error('editAmount')
                <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text>
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveBudget" variant="primary">
                    {{ $editingBudgetId ? 'Update' : 'Add Budget' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
