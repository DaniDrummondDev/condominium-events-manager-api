<?php

declare(strict_types=1);

namespace Domain\Unit\Enums;

enum BlockStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
