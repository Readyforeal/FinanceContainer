<?php

use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;

new class extends Component {
    public function assignCategory(int $transactionId, int $categoryId): void
    {
        $transaction = Transaction::findOrFail($transactionId);
        $category = Category::findOrFail($categoryId);

        $transaction->update([
            'category_id' => $categoryId,
            'budget_bucket' => $category->default_bucket,
            'needs_review' => false,
            'categorization_confidence' => 1.00,
        ]);
    }

    public function with(): array
    {
        return [
            'flaggedTransactions' => Transaction::where('needs_review', true)
                ->with(['account', 'category'])
                ->latest('date')
                ->limit(5)
                ->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }
};
?>

<div class="bubble-assistant rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
    @if ($flaggedTransactions->isNotEmpty())
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <x-lucide-alert-triangle class="w-4 h-4 text-amber-500" />
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Needs Review</h2>
            </div>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 text-xs font-semibold">
                {{ $flaggedTransactions->count() }}
            </span>
        </div>

        <div class="space-y-3">
            @foreach ($flaggedTransactions as $transaction)
                <div class="flex items-center gap-3 py-2 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                            {{ $transaction->merchant_name ?? $transaction->description }}
                        </p>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">
                            {{ $transaction->date->format('M j') }}
                            &middot; ${{ number_format($transaction->amount, 2) }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <select
                            wire:change="assignCategory({{ $transaction->id }}, $event.target.value)"
                            class="text-xs bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1 text-zinc-600 dark:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Assign category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ $transaction->category_id === $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex items-center gap-3">
            <x-lucide-check-circle class="w-8 h-8 text-green-500 dark:text-green-400 flex-shrink-0" />
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">All Clear</h2>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">No transactions need review</p>
            </div>
        </div>
    @endif
</div>
