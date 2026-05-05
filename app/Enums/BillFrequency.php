<?php

namespace App\Enums;

enum BillFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnually = 'semi_annually';
    case Annually = 'annually';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';

    public function label(): string
    {
        return match($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnually => 'Semi-Annually',
            self::Annually => 'Annually',
            self::Weekly => 'Weekly',
            self::Biweekly => 'Biweekly',
        };
    }
}
