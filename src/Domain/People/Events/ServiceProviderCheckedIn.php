<?php

declare(strict_types=1);

namespace Domain\People\Events;

use DateTimeImmutable;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ServiceProviderCheckedIn implements DomainEvent
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public string $visitId,
        public string $serviceProviderId,
        public string $unitId,
        public string $checkedInBy,
    ) {
        $this->occurredAt = new DateTimeImmutable;
    }

    public function eventName(): string
    {
        return 'service_provider.checked_in';
    }

    public function aggregateId(): Uuid
    {
        return Uuid::fromString($this->visitId);
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'visit_id' => $this->visitId,
            'service_provider_id' => $this->serviceProviderId,
            'unit_id' => $this->unitId,
            'checked_in_by' => $this->checkedInBy,
        ];
    }
}
