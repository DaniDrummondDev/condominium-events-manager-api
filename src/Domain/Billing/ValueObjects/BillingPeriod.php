<?php

declare(strict_types=1);

namespace Domain\Billing\ValueObjects;

use DateTimeImmutable;
use Domain\Billing\Enums\BillingCycle;
use Domain\Shared\Exceptions\DomainException;

final readonly class BillingPeriod
{
    public function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new DomainException(
                'Billing period end must be after start',
                'INVALID_BILLING_PERIOD',
                [
                    'start' => $start->format('Y-m-d H:i:s'),
                    'end' => $end->format('Y-m-d H:i:s'),
                ],
            );
        }
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function daysRemaining(DateTimeImmutable $now): int
    {
        if ($now >= $this->end) {
            return 0;
        }

        $diff = $now->diff($this->end);

        return (int) $diff->days;
    }

    public function totalDays(): int
    {
        $diff = $this->start->diff($this->end);

        return (int) $diff->days;
    }

    public function isActive(DateTimeImmutable $now): bool
    {
        return $now >= $this->start && $now < $this->end;
    }

    public function next(BillingCycle $cycle): self
    {
        $modifier = match ($cycle) {
            BillingCycle::Monthly => '+1 month',
            BillingCycle::Yearly => '+1 year',
        };

        return new self(
            $this->end,
            $this->end->modify($modifier),
        );
    }

    public function prorataFraction(DateTimeImmutable $now): float
    {
        $totalDays = $this->totalDays();

        if ($totalDays === 0) {
            return 0.0;
        }

        $remaining = $this->daysRemaining($now);

        return round($remaining / $totalDays, 4);
    }
}
