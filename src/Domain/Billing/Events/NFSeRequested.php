<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class NFSeRequested implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $nfseId,
        private Uuid $tenantId,
        private Uuid $invoiceId,
        private int $totalAmountInCents,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.nfse.requested';
    }

    public function aggregateId(): Uuid
    {
        return $this->nfseId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'nfse_id' => $this->nfseId->value(),
            'tenant_id' => $this->tenantId->value(),
            'invoice_id' => $this->invoiceId->value(),
            'total_amount_in_cents' => $this->totalAmountInCents,
        ];
    }
}
