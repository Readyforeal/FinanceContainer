<?php

namespace App\Models;

use App\Enums\PlaidConnectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaidConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_token',
        'item_id',
        'institution_name',
        'cursor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'status' => PlaidConnectionStatus::class,
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
