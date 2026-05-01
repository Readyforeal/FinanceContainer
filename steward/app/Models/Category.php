<?php

namespace App\Models;

use App\Enums\BudgetBucket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'default_bucket',
        'is_essential',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'default_bucket' => BudgetBucket::class,
            'is_essential' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function averageSpend(int $months = 3): float
    {
        $since = now()->subMonths($months);
        $total = abs($this->transactions()->where('date', '>=', $since)->where('amount', '<', 0)->sum('amount'));

        return round($total / max($months, 1), 2);
    }
}
