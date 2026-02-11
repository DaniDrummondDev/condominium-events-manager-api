<?php

declare(strict_types=1);

namespace Domain\Auth\Enums;

enum PlatformRole: string
{
    case PlatformOwner = 'platform_owner';
    case PlatformAdmin = 'platform_admin';
    case PlatformSupport = 'platform_support';

    public function requiresMfa(): bool
    {
        return match ($this) {
            self::PlatformOwner, self::PlatformAdmin => true,
            self::PlatformSupport => false,
        };
    }
}
