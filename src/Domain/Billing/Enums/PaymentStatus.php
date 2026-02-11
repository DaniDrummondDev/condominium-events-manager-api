<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Authorized, self::Paid, self::Failed, self::Canceled],
            self::Authorized => [self::Paid, self::Failed, self::Canceled],
            self::Paid => [self::Refunded],
            self::Failed => [],
            self::Canceled => [],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Paid;
    }
}
