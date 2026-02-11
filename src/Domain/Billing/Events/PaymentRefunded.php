<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PaymentRefunded implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $paymentId,
        private int $amountInCents,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.payment.refunded';
    }

    public function aggregateId(): Uuid
    {
        return $this->paymentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->paymentId->value(),
            'amount_in_cents' => $this->amountInCents,
        ];
    }
}
