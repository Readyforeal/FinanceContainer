<?php

namespace App\Livewire\Transactions;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
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

    public function render()
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

        $transactions = $query->paginate(25);
        $accounts = Account::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        return view('livewire.transactions.transaction-list', compact('transactions', 'accounts', 'categories'));
    }
}
