<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaid_connection_id',
        'plaid_account_id',
        'name',
        'type',
        'current_balance',
        'available_balance',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'current_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }

    public function plaidConnection(): BelongsTo
    {
        return $this->belongsTo(PlaidConnection::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
