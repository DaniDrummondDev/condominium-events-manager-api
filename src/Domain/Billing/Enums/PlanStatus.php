<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum PlanStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function isAvailable(): bool
    {
        return $this === self::Active;
    }
}
