<?php

declare(strict_types=1);

namespace Domain\Governance\Enums;

enum ViolationStatus: string
{
    case Open = 'open';
    case Contested = 'contested';
    case Upheld = 'upheld';
    case Revoked = 'revoked';

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Upheld, self::Revoked, self::Contested],
            self::Contested => [self::Upheld, self::Revoked],
            self::Upheld, self::Revoked => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Upheld, self::Revoked], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Contested], true);
    }
}
