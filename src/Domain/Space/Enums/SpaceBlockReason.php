<?php

declare(strict_types=1);

namespace Domain\Space\Enums;

enum SpaceBlockReason: string
{
    case Maintenance = 'maintenance';
    case Holiday = 'holiday';
    case Event = 'event';
    case Administrative = 'administrative';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Manutenção',
            self::Holiday => 'Feriado',
            self::Event => 'Evento',
            self::Administrative => 'Administrativo',
        };
    }
}
