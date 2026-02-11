<?php

declare(strict_types=1);

namespace Domain\Tenant\Enums;

enum TenantStatus: string
{
    case Prospect = 'prospect';
    case Trial = 'trial';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Canceled = 'canceled';
    case Archived = 'archived';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Prospect => [self::Trial, self::Provisioning],
            self::Trial => [self::Provisioning, self::Canceled],
            self::Provisioning => [self::Active, self::Prospect],
            self::Active => [self::PastDue, self::Suspended, self::Canceled],
            self::PastDue => [self::Active, self::Suspended, self::Canceled],
            self::Suspended => [self::Active, self::Canceled],
            self::Canceled => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::Active, self::Trial, self::PastDue], true);
    }
}
