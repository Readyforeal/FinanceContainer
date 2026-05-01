<?php
namespace App\Enums;

enum BudgetBucket: string
{
    case Needs = 'needs';
    case Wants = 'wants';
    case Savings = 'savings';
    case Income = 'income';
    case Transfer = 'transfer';

    public function isSpending(): bool
    {
        return in_array($this, [self::Needs, self::Wants, self::Savings]);
    }
}
