<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use Domain\Shared\ValueObjects\Uuid;

class DunningPolicy
{
    /**
     * @param  array<int>  $retryIntervals
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly string $name,
        private readonly int $maxRetries,
        private readonly array $retryIntervals,
        private readonly int $suspendAfterDays,
        private readonly int $cancelAfterDays,
        private readonly bool $isDefault,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * @return array<int>
     */
    public function retryIntervals(): array
    {
        return $this->retryIntervals;
    }

    public function suspendAfterDays(): int
    {
        return $this->suspendAfterDays;
    }

    public function cancelAfterDays(): int
    {
        return $this->cancelAfterDays;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function retryIntervalForAttempt(int $attempt): ?int
    {
        if ($attempt < 1 || $attempt > $this->maxRetries) {
            return null;
        }

        $index = $attempt - 1;

        return $this->retryIntervals[$index] ?? null;
    }

    public function shouldSuspend(int $daysPastDue): bool
    {
        return $daysPastDue >= $this->suspendAfterDays;
    }

    public function shouldCancel(int $daysPastDue): bool
    {
        return $daysPastDue >= $this->cancelAfterDays;
    }
}
