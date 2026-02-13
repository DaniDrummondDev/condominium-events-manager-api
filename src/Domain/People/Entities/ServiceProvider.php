<?php

declare(strict_types=1);

namespace Domain\People\Entities;

use DateTimeImmutable;
use Domain\People\Enums\ServiceProviderStatus;
use Domain\People\Enums\ServiceType;
use Domain\Shared\ValueObjects\Uuid;

class ServiceProvider
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private ?string $companyName,
        private string $name,
        private readonly string $document,
        private ?string $phone,
        private ServiceType $serviceType,
        private ServiceProviderStatus $status,
        private ?string $notes,
        private readonly Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        ?string $companyName,
        string $name,
        string $document,
        ?string $phone,
        ServiceType $serviceType,
        ?string $notes,
        Uuid $createdBy,
    ): self {
        return new self(
            id: $id,
            companyName: $companyName,
            name: $name,
            document: $document,
            phone: $phone,
            serviceType: $serviceType,
            status: ServiceProviderStatus::Active,
            notes: $notes,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable,
        );
    }

    // ── State Transitions ───────────────────────────────────────

    public function deactivate(): void
    {
        $this->status = ServiceProviderStatus::Inactive;
    }

    public function block(): void
    {
        $this->status = ServiceProviderStatus::Blocked;
    }

    public function activate(): void
    {
        $this->status = ServiceProviderStatus::Active;
    }

    // ── Mutations ────────────────────────────────────────────────

    public function update(
        ?string $companyName,
        string $name,
        ?string $phone,
        ServiceType $serviceType,
        ?string $notes,
    ): void {
        $this->companyName = $companyName;
        $this->name = $name;
        $this->phone = $phone;
        $this->serviceType = $serviceType;
        $this->notes = $notes;
    }

    // ── Business Logic ──────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canBeLinkedToVisits(): bool
    {
        return $this->status->canBeLinkedToVisits();
    }

    // ── Getters ─────────────────────────────────────────────────

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyName(): ?string
    {
        return $this->companyName;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function document(): string
    {
        return $this->document;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function serviceType(): ServiceType
    {
        return $this->serviceType;
    }

    public function status(): ServiceProviderStatus
    {
        return $this->status;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
