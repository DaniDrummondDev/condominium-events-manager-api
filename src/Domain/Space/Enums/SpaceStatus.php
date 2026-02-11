<?php

declare(strict_types=1);

namespace Domain\Space\Enums;

enum SpaceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canAcceptReservations(): bool
    {
        return $this === self::Active;
    }
}
