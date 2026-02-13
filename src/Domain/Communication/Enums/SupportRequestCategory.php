<?php

declare(strict_types=1);

namespace Domain\Communication\Enums;

enum SupportRequestCategory: string
{
    case Maintenance = 'maintenance';
    case Noise = 'noise';
    case Security = 'security';
    case General = 'general';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Manutenção',
            self::Noise => 'Barulho',
            self::Security => 'Segurança',
            self::General => 'Geral',
            self::Other => 'Outro',
        };
    }
}
