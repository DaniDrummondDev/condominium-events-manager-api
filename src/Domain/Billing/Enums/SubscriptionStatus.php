<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case GracePeriod = 'grace_period';
    case Suspended = 'suspended';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::Canceled],
            self::Active => [self::PastDue, self::Canceled],
            self::PastDue => [self::Active, self::GracePeriod, self::Canceled],
            self::GracePeriod => [self::Active, self::Suspended, self::Canceled],
            self::Suspended => [self::Active, self::Expired],
            self::Canceled => [self::Expired],
            self::Expired => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    public function allowsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue, self::GracePeriod], true);
    }
}
