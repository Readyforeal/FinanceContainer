<?php

namespace App\Models;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'plaid_transaction_id',
        'amount',
        'date',
        'merchant_name',
        'description',
        'plaid_category',
        'category_id',
        'categorization_confidence',
        'needs_review',
        'is_recurring',
        'budget_bucket',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'categorization_confidence' => 'decimal:2',
            'needs_review' => 'boolean',
            'is_recurring' => 'boolean',
            'budget_bucket' => BudgetBucket::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
