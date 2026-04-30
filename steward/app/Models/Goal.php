<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'target_amount',
        'current_amount',
        'target_date',
        'priority',
        'bucket',
        'category_id',
        'notes',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'target_date' => 'date',
            'is_completed' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function progressPercent(): float
    {
        $target = (float) $this->target_amount;

        if ($target === 0.0) {
            return 0.0;
        }

        return round(((float) $this->current_amount / $target) * 100, 1);
    }

    public function remaining(): float
    {
        return max(0.0, (float) $this->target_amount - (float) $this->current_amount);
    }

    public function monthlySavingsNeeded(): ?float
    {
        if (! $this->target_date) {
            return null;
        }

        $monthsLeft = (int) round(now()->diffInMonths($this->target_date, false));

        if ($monthsLeft <= 0) {
            return $this->remaining();
        }

        return round($this->remaining() / $monthsLeft, 2);
    }
}
