<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class NFSeDenied implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        private Uuid $nfseId,
        private Uuid $tenantId,
        private Uuid $invoiceId,
        private string $errorMessage,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'billing.nfse.denied';
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
            'error_message' => $this->errorMessage,
        ];
    }
}
