<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'amount',
        'frequency',
        'next_pay_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_pay_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function monthlyAmount(): float
    {
        return match ($this->frequency) {
            'weekly' => round($this->amount * 52 / 12, 2),
            'biweekly' => round($this->amount * 26 / 12, 2),
            'monthly' => (float) $this->amount,
            default => 0,
        };
    }
}
