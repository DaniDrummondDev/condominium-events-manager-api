<?php

declare(strict_types=1);

namespace Domain\Unit\Enums;

enum ResidentRole: string
{
    case Owner = 'owner';
    case TenantResident = 'tenant_resident';
    case Dependent = 'dependent';
    case Authorized = 'authorized';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::TenantResident => 'Inquilino',
            self::Dependent => 'Dependente',
            self::Authorized => 'Autorizado',
        };
    }

    public function canManageUnit(): bool
    {
        return in_array($this, [self::Owner, self::TenantResident]);
    }
}
