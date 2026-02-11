<?php

declare(strict_types=1);

namespace Domain\Unit\Enums;

enum UnitType: string
{
    case Apartment = 'apartment';
    case House = 'house';
    case Store = 'store';
    case Office = 'office';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Apartment => 'Apartamento',
            self::House => 'Casa',
            self::Store => 'Loja',
            self::Office => 'Sala Comercial',
            self::Other => 'Outro',
        };
    }
}
