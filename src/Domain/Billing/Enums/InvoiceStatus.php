<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case PastDue = 'past_due';
    case Void = 'void';
    case Uncollectible = 'uncollectible';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open],
            self::Open => [self::Paid, self::PastDue, self::Void],
            self::PastDue => [self::Paid, self::Uncollectible],
            self::Paid => [],
            self::Void => [],
            self::Uncollectible => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Void, self::Uncollectible], true);
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::Open, self::PastDue], true);
    }
}
