<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $accountFilter = null;
    public ?int $categoryFilter = null;
    public ?bool $reviewFilter = null;
    public string $search = '';
    public string $sortField = 'date';
    public string $sortDirection = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedReviewFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

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
        $query = Transaction::with(['account', 'category'])
            ->when($this->accountFilter, fn ($q) => $q->where('account_id', $this->accountFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->reviewFilter !== null, fn ($q) => $q->where('needs_review', $this->reviewFilter))
            ->when($this->search, function ($q) {
                $search = '%' . $this->search . '%';
                $q->where(function ($q2) use ($search) {
                    $q2->where('merchant_name', 'ilike', $search)
                        ->orWhere('description', 'ilike', $search);
                });
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return [
            'transactions' => $query->paginate(25),
            'accounts' => Account::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-6">
        <input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Search transactions..."
            class="bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-zinc-400 dark:placeholder-zinc-500"
        />

        <select
            wire:model.live="accountFilter"
            class="bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
        >
            <option value="">All Accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->name }}</option>
            @endforeach
        </select>

        <select
            wire:model.live="categoryFilter"
            class="bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
        >
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>

        <label class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400 cursor-pointer">
            <input
                wire:model.live="reviewFilter"
                type="checkbox"
                value="1"
                class="rounded border-zinc-400 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-indigo-500"
            />
            Needs Review
        </label>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-300 dark:border-zinc-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-300 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wide">
                    <th class="text-left px-4 py-3">
                        <button wire:click="sortBy('date')" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-200">
                            Date
                            @if ($sortField === 'date')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    <th class="text-left px-4 py-3">
                        <button wire:click="sortBy('merchant_name')" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-200">
                            Merchant
                            @if ($sortField === 'merchant_name')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    <th class="text-left px-4 py-3">Account</th>
                    <th class="text-left px-4 py-3">Category</th>
                    <th class="text-right px-4 py-3">
                        <button wire:click="sortBy('amount')" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-200 ml-auto">
                            Amount
                            @if ($sortField === 'amount')
                                <x-lucide-chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="w-3 h-3" />
                            @endif
                        </button>
                    </th>
                    <th class="text-center px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($transactions as $transaction)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-colors {{ $transaction->needs_review ? 'bg-yellow-50 dark:bg-yellow-950/20' : '' }}">
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                            {{ $transaction->date->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3 text-zinc-900 dark:text-zinc-100">
                            {{ $transaction->merchant_name ?? $transaction->description ?? 'Unknown' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $transaction->account?->name ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($transaction->needs_review)
                                <select
                                    wire:change="assignCategory({{ $transaction->id }}, $event.target.value)"
                                    class="bg-zinc-200 dark:bg-zinc-700 border border-yellow-600 text-zinc-900 dark:text-zinc-100 text-xs rounded px-2 py-1"
                                >
                                    <option value="">-- Assign Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ $transaction->category_id === $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">{{ $transaction->category?->name ?? '-' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono">
                            <span class="{{ $transaction->amount < 0 ? 'text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $transaction->amount < 0 ? '-' : '' }}${{ number_format(abs($transaction->amount), 2) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($transaction->needs_review)
                                <span class="inline-flex items-center gap-1 text-xs bg-yellow-900/50 text-yellow-400 border border-yellow-700 px-2 py-0.5 rounded-full">
                                    <x-lucide-flag class="w-3 h-3" />
                                    Review
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs bg-green-900/50 text-green-400 px-2 py-0.5 rounded-full">
                                    <x-lucide-check class="w-3 h-3" />
                                    OK
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-400 dark:text-zinc-500">
                            No transactions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($transactions->hasPages())
        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    @endif
</div>
