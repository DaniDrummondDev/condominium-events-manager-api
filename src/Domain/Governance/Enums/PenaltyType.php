<?php

declare(strict_types=1);

namespace Domain\Governance\Enums;

enum PenaltyType: string
{
    case Warning = 'warning';
    case TemporaryBlock = 'temporary_block';
    case PermanentBlock = 'permanent_block';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Advertência',
            self::TemporaryBlock => 'Bloqueio Temporário',
            self::PermanentBlock => 'Bloqueio Permanente',
        };
    }

    public function isBlocking(): bool
    {
        return in_array($this, [self::TemporaryBlock, self::PermanentBlock], true);
    }
}
