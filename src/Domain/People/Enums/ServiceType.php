<?php

declare(strict_types=1);

namespace Domain\People\Enums;

enum ServiceType: string
{
    case Buffet = 'buffet';
    case Cleaning = 'cleaning';
    case Decoration = 'decoration';
    case Dj = 'dj';
    case Security = 'security';
    case Maintenance = 'maintenance';
    case Moving = 'moving';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Buffet => 'Buffet',
            self::Cleaning => 'Limpeza',
            self::Decoration => 'Decoração',
            self::Dj => 'DJ',
            self::Security => 'Segurança',
            self::Maintenance => 'Manutenção',
            self::Moving => 'Mudança',
            self::Other => 'Outro',
        };
    }
}
