<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum NFSeStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Denied = 'denied';
    case Cancelled = 'cancelled';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Processing],
            self::Processing => [self::Authorized, self::Denied],
            self::Authorized => [self::Cancelled],
            self::Denied => [self::Draft],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cancelled], true);
    }

    public function canRetry(): bool
    {
        return $this === self::Denied;
    }
}
