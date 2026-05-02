<?php

namespace App\Models;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'budgeted_amount',
        'bucket',
    ];

    protected function casts(): array
    {
        return [
            'budgeted_amount' => 'decimal:2',
            'bucket' => BudgetBucket::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
