<?php

declare(strict_types=1);

namespace Domain\Communication\Enums;

enum SupportRequestStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

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
            self::Open => [self::InProgress, self::Closed],
            self::InProgress => [self::Resolved, self::Closed],
            self::Resolved => [self::Open, self::Closed],
            self::Closed => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::InProgress => 'Em Andamento',
            self::Resolved => 'Resolvida',
            self::Closed => 'Fechada',
        };
    }
}
