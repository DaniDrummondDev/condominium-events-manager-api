<?php

declare(strict_types=1);

namespace Domain\Communication\Enums;

enum AudienceType: string
{
    case All = 'all';
    case Block = 'block';
    case Units = 'units';

    public function requiresIds(): bool
    {
        return $this !== self::All;
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todos',
            self::Block => 'Bloco',
            self::Units => 'Unidades',
        };
    }
}
