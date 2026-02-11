<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class PaymentFailed implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $paymentId,
        private Uuid $invoiceId,
        private string $reason,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.payment.failed';
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
            'invoice_id' => $this->invoiceId->value(),
            'reason' => $this->reason,
        ];
    }
}
