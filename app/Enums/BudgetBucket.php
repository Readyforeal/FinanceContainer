<?php
namespace App\Enums;

enum BudgetBucket: string
{
    case Needs = 'needs';
    case Wants = 'wants';
    case Savings = 'savings';
}
