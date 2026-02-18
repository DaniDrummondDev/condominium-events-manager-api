<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Semiannual = 'semiannual';
    case Yearly = 'yearly';

    public function periodDays(): int
    {
        return match ($this) {
            self::Monthly => 30,
            self::Semiannual => 180,
            self::Yearly => 365,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Semiannual => 'Semestral',
            self::Yearly => 'Anual',
        };
    }
}
