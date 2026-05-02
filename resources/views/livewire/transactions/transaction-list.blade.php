<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Attributes\On;
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

    public array $selectedIds = [];
    public ?int $bulkCategoryId = null;

    // Transaction form
    public ?int $editingTransactionId = null;
    public string $formMerchant = '';
    public string $formDescription = '';
    public string $formAmount = '';
    public string $formDate = '';
    public ?int $formAccountId = null;
    public ?int $formCategoryId = null;
    public string $formType = 'expense';

    public function mount(): void
    {
        $this->js("setTimeout(() => window.dispatchEvent(new CustomEvent('set-dock-action', { detail: { icon: 'plus' } })), 100)");
    }

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

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_filter($this->selectedIds, fn ($i) => $i !== $id));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function selectAllVisible(): void
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

        $this->selectedIds = $query->paginate(25)->pluck('id')->toArray();
    }

    public function deselectAll(): void
    {
        $this->selectedIds = [];
    }

    public function bulkCategorize(): void
    {
        if (! $this->bulkCategoryId) {
            $this->addError('bulkCategory', 'Please select a category.');
            return;
        }

        $category = Category::findOrFail($this->bulkCategoryId);

        Transaction::whereIn('id', $this->selectedIds)->update([
            'category_id' => $category->id,
            'budget_bucket' => $category->default_bucket,
            'needs_review' => false,
            'categorization_confidence' => 1.0,
        ]);

        $this->selectedIds = [];
        $this->bulkCategoryId = null;
        $this->resetValidation('bulkCategory');
    }

    #[On('dock-action')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formDate = now()->format('Y-m-d');
        $this->formAccountId = Account::first()?->id;
        $this->modal('transaction-editor')->show();
    }

    public function openEdit(int $id): void
    {
        $txn = Transaction::findOrFail($id);
        $this->editingTransactionId = $txn->id;
        $this->formMerchant = $txn->merchant_name ?? '';
        $this->formDescription = $txn->description ?? '';
        $this->formAmount = (string) abs($txn->amount);
        $this->formDate = $txn->date->format('Y-m-d');
        $this->formAccountId = $txn->account_id;
        $this->formCategoryId = $txn->category_id;
        $this->formType = $txn->amount > 0 ? 'income' : 'expense';
        $this->modal('transaction-editor')->show();
    }

    public function saveTransaction(): void
    {
        $this->validate([
            'formAmount' => ['required', 'numeric', 'gt:0'],
            'formDate' => ['required', 'date'],
            'formAccountId' => ['required', 'exists:accounts,id'],
        ]);

        $amount = (float) $this->formAmount;
        if ($this->formType === 'expense') {
            $amount = -$amount;
        }

        $category = $this->formCategoryId ? Category::find($this->formCategoryId) : null;

        $data = [
            'merchant_name' => $this->formMerchant ?: null,
            'description' => $this->formDescription ?: $this->formMerchant ?: 'Manual entry',
            'amount' => $amount,
            'date' => $this->formDate,
            'account_id' => $this->formAccountId,
            'category_id' => $this->formCategoryId,
            'budget_bucket' => $category?->default_bucket,
            'needs_review' => $category === null,
            'categorization_confidence' => $category ? 1.0 : 0,
        ];

        if ($this->editingTransactionId) {
            Transaction::findOrFail($this->editingTransactionId)->update($data);
        } else {
            $data['plaid_transaction_id'] = 'manual_' . uniqid();
            Transaction::create($data);
        }

        $this->modal('transaction-editor')->close();
        $this->resetForm();
    }

    public function deleteTransaction(int $id): void
    {
        Transaction::findOrFail($id)->delete();
    }

    private function resetForm(): void
    {
        $this->editingTransactionId = null;
        $this->formMerchant = '';
        $this->formDescription = '';
        $this->formAmount = '';
        $this->formDate = '';
        $this->formAccountId = null;
        $this->formCategoryId = null;
        $this->formType = 'expense';
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
    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search transactions..." size="sm" class="w-full sm:max-w-xs" />

        <div class="flex gap-3">
            <flux:select wire:model.live="accountFilter" size="sm" class="flex-1 sm:flex-none sm:max-w-48">
                <flux:select.option value="">All Accounts</flux:select.option>
                @foreach ($accounts as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="categoryFilter" size="sm" class="flex-1 sm:flex-none sm:max-w-48">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:checkbox wire:model.live="reviewFilter" label="Needs Review" />

        <div class="hidden lg:block lg:ml-auto">
            <flux:button wire:click="openCreate" variant="primary" icon="plus" size="sm">Add Transaction</flux:button>
        </div>
    </div>

    {{-- Bulk action bar (floating on mobile) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-16 left-3 right-3 z-30 lg:relative lg:bottom-auto lg:left-auto lg:right-auto lg:mb-4">
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl shadow-lg lg:shadow-none lg:bg-zinc-50 lg:dark:bg-zinc-900">
                <flux:text size="sm" class="font-medium">
                    {{ count($selectedIds) }} selected
                </flux:text>

                <flux:select wire:model="bulkCategoryId" size="sm" class="max-w-48">
                    <flux:select.option value="">Select category...</flux:select.option>
                    @foreach ($categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @error('bulkCategory')
                    <flux:text size="xs" class="text-red-400">{{ $message }}</flux:text>
                @enderror

                <flux:button wire:click="bulkCategorize" variant="primary" size="sm">Categorize</flux:button>
                <flux:button wire:click="deselectAll" variant="ghost" size="sm">Clear</flux:button>
            </div>
        </div>
    @endif

    {{-- Desktop table --}}
    <div class="hidden lg:block">
        <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-8">
                    <flux:checkbox wire:click="selectAllVisible" />
                </flux:table.column>
                <flux:table.column>
                    <button wire:click="sortBy('date')" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-200">
                        Date
                        @if ($sortField === 'date')
                            <flux:icon :icon="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>
                    <button wire:click="sortBy('merchant_name')" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-200">
                        Merchant
                        @if ($sortField === 'merchant_name')
                            <flux:icon :icon="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column class="text-right">
                    <button wire:click="sortBy('amount')" class="flex items-center gap-1 hover:text-zinc-800 dark:hover:text-zinc-200 ml-auto">
                        Amount
                        @if ($sortField === 'amount')
                            <flux:icon :icon="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3" />
                        @endif
                    </button>
                </flux:table.column>
                <flux:table.column class="text-center">Status</flux:table.column>
                <flux:table.column class="w-16"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($transactions as $transaction)
                    <flux:table.row class="{{ $transaction->needs_review ? 'bg-yellow-50 dark:bg-yellow-950/20' : '' }}">
                        <flux:table.cell class="w-8">
                            <flux:checkbox wire:click="toggleSelect({{ $transaction->id }})" :checked="in_array($transaction->id, $selectedIds)" />
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                            {{ $transaction->date->format('M j, Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->merchant_name ?? $transaction->description ?? 'Unknown' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500 dark:text-zinc-400">
                            {{ $transaction->account?->name ?? '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <select
                                wire:change="assignCategory({{ $transaction->id }}, $event.target.value)"
                                class="appearance-none bg-transparent border-0 text-sm rounded-md py-0.5 pr-6 pl-1.5 -ml-1.5 cursor-pointer transition-colors
                                    {{ $transaction->needs_review
                                        ? 'text-yellow-700 dark:text-yellow-400 font-medium'
                                        : 'text-zinc-600 dark:text-zinc-400' }}
                                    hover:bg-zinc-100 dark:hover:bg-zinc-700/50
                                    focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:bg-zinc-50 dark:focus:bg-zinc-700"
                                style="background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E&quot;); background-repeat: no-repeat; background-position: right 4px center;"
                            >
                                @if (! $transaction->category_id)
                                    <option value="">-- Assign --</option>
                                @endif
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($transaction->category_id === $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono">
                            <span class="{{ $transaction->amount < 0 ? 'text-green-600 dark:text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $transaction->amount < 0 ? '-' : '' }}${{ number_format(abs($transaction->amount), 2) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            @if ($transaction->needs_review)
                                <flux:badge color="yellow" size="sm" icon="flag">Review</flux:badge>
                            @else
                                <flux:badge color="green" size="sm" icon="circle-check">OK</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                <flux:button variant="subtle" size="xs" icon="pencil" wire:click="openEdit({{ $transaction->id }})" />
                                <flux:button variant="subtle" size="xs" icon="trash-2" wire:click="deleteTransaction({{ $transaction->id }})" wire:confirm="Delete this transaction?" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center py-12">
                            <flux:text class="text-zinc-400 dark:text-zinc-500">No transactions found.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Mobile card list --}}
    <div class="lg:hidden space-y-2">
        @forelse ($transactions as $transaction)
            <div wire:click="openEdit({{ $transaction->id }})" class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer active:bg-zinc-50 dark:active:bg-zinc-800 {{ $transaction->needs_review ? 'border-yellow-300 dark:border-yellow-700 bg-yellow-50 dark:bg-yellow-950/20' : 'border-zinc-200 dark:border-zinc-700' }}">
                <flux:checkbox wire:click.stop="toggleSelect({{ $transaction->id }})" :checked="in_array($transaction->id, $selectedIds)" class="shrink-0" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <flux:text size="sm" class="font-medium truncate">
                            {{ $transaction->merchant_name ?? $transaction->description ?? 'Unknown' }}
                        </flux:text>
                        <span class="shrink-0 font-mono text-sm font-semibold {{ $transaction->amount < 0 ? 'text-green-600 dark:text-green-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                            {{ $transaction->amount < 0 ? '-' : '' }}${{ number_format(abs($transaction->amount), 2) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 mt-1">
                        <flux:text size="xs">
                            {{ $transaction->date->format('M j') }}
                            &middot;
                            {{ $transaction->category?->name ?? 'Uncategorized' }}
                        </flux:text>
                        @if ($transaction->needs_review)
                            <flux:badge color="yellow" size="sm">Review</flux:badge>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <flux:text>No transactions found.</flux:text>
            </div>
        @endforelse
    </div>

    @if ($transactions->hasPages())
        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    @endif

    {{-- Transaction editor modal --}}
    <flux:modal name="transaction-editor" class="w-full md:w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingTransactionId ? 'Edit Transaction' : 'Add Transaction' }}</flux:heading>
                <flux:text class="mt-1">{{ $editingTransactionId ? 'Update transaction details.' : 'Manually enter a transaction.' }}</flux:text>
            </div>

            <div>
                <flux:label class="mb-2">Type</flux:label>
                <div class="flex gap-2">
                    <flux:button wire:click="$set('formType', 'expense')" :variant="$formType === 'expense' ? 'primary' : 'subtle'" size="sm" class="flex-1">Expense</flux:button>
                    <flux:button wire:click="$set('formType', 'income')" :variant="$formType === 'income' ? 'primary' : 'subtle'" size="sm" class="flex-1">Income</flux:button>
                </div>
            </div>

            <flux:input wire:model="formMerchant" label="Merchant" placeholder="e.g. Walmart, Starbucks" />

            <flux:input wire:model="formDescription" label="Description" placeholder="Optional details" />

            <flux:input wire:model="formAmount" type="number" label="Amount" min="0.01" step="0.01" placeholder="0.00" />
            @error('formAmount') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <flux:input wire:model="formDate" type="date" label="Date" />
            @error('formDate') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <flux:select wire:model="formAccountId" label="Account">
                @foreach ($accounts as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('formAccountId') <flux:text size="xs" class="text-red-500">{{ $message }}</flux:text> @enderror

            <flux:select wire:model="formCategoryId" label="Category" placeholder="Select category...">
                <flux:select.option value="">None</flux:select.option>
                @foreach ($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-between">
                @if ($editingTransactionId)
                    <flux:button wire:click="deleteTransaction({{ $editingTransactionId }})" wire:confirm="Delete this transaction?" variant="danger" size="sm">Delete</flux:button>
                @else
                    <div></div>
                @endif
                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="saveTransaction" variant="primary">
                        {{ $editingTransactionId ? 'Update' : 'Add' }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
