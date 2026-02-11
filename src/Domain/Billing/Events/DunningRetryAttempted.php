<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class DunningRetryAttempted implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $invoiceId,
        private int $attemptNumber,
        private bool $success,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.dunning.retry_attempted';
    }

    public function aggregateId(): Uuid
    {
        return $this->invoiceId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoiceId->value(),
            'attempt_number' => $this->attemptNumber,
            'success' => $this->success,
        ];
    }
}
