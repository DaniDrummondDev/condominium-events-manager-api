<?php

declare(strict_types=1);

namespace Domain\People\Enums;

enum ServiceProviderStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canBeLinkedToVisits(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
            self::Blocked => 'Bloqueado',
        };
    }
}
