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

<flux:card>
    @if ($flaggedTransactions->isNotEmpty())
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <flux:icon.triangle-alert variant="mini" class="text-amber-500" />
                <flux:heading size="sm">Needs Review</flux:heading>
            </div>
            <flux:badge color="amber" size="sm">{{ $flaggedTransactions->count() }}</flux:badge>
        </div>

        <div class="space-y-3">
            @foreach ($flaggedTransactions as $transaction)
                <div class="flex items-center gap-3 py-2 border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium truncate">
                            {{ $transaction->merchant_name ?? $transaction->description }}
                        </flux:text>
                        <flux:text size="xs">
                            {{ $transaction->date->format('M j') }}
                            &middot; {{ $transaction->amount < 0 ? '-' : '+' }}${{ number_format(abs($transaction->amount), 2) }}
                        </flux:text>
                    </div>
                    <div class="flex-shrink-0">
                        <flux:select size="sm" wire:change="assignCategory({{ $transaction->id }}, $event.target.value)">
                            <flux:select.option value="">Assign category</flux:select.option>
                            @foreach ($categories as $category)
                                <flux:select.option value="{{ $category->id }}" :selected="$transaction->category_id === $category->id">
                                    {{ $category->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex items-center gap-3">
            <flux:icon.circle-check class="size-8 text-green-500 dark:text-green-400 flex-shrink-0" />
            <div>
                <flux:heading size="sm">All Clear</flux:heading>
                <flux:text size="xs">No transactions need review</flux:text>
            </div>
        </div>
    @endif
</flux:card>
