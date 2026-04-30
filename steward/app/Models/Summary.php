<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'period_start',
        'period_end',
        'total_income',
        'total_spent',
        'needs_spent',
        'wants_spent',
        'savings_spent',
        'ai_analysis',
        'ai_advice',
        'habit_flags',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_income' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'needs_spent' => 'decimal:2',
            'wants_spent' => 'decimal:2',
            'savings_spent' => 'decimal:2',
            'habit_flags' => 'array',
        ];
    }
}
