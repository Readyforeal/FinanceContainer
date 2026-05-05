<?php

namespace App\Models;

use App\Enums\BillFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'payee', 'merchant_pattern', 'amount', 'is_fixed',
        'due_day', 'frequency', 'is_autopay', 'account_id',
        'category_id', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_fixed' => 'boolean',
            'is_autopay' => 'boolean',
            'is_active' => 'boolean',
            'frequency' => BillFrequency::class,
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

    public function dueDateForMonth(Carbon $month): Carbon
    {
        $day = min($this->due_day, $month->copy()->endOfMonth()->day);
        return $month->copy()->startOfMonth()->addDays($day - 1);
    }

    public function matchingTransaction(Carbon $periodStart, Carbon $periodEnd): ?Transaction
    {
        return Transaction::where('merchant_name', 'ilike', '%' . $this->merchant_pattern . '%')
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('amount', '<', 0)
            ->orderByDesc('date')
            ->first();
    }

    public function statusForMonth(Carbon $month): string
    {
        $dueDate = $this->dueDateForMonth($month);
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $payment = $this->matchingTransaction($periodStart, $periodEnd);

        if ($payment) {
            return 'paid';
        }

        if (now()->gt($dueDate) && now()->month === $month->month && now()->year === $month->year) {
            return 'overdue';
        }

        if (now()->diffInDays($dueDate, false) <= 5 && now()->diffInDays($dueDate, false) >= 0) {
            return 'due_soon';
        }

        return 'upcoming';
    }
}
