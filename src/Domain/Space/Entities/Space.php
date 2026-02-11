<?php

declare(strict_types=1);

namespace Domain\Space\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Enums\SpaceStatus;
use Domain\Space\Enums\SpaceType;
use Domain\Space\Events\SpaceCreated;
use Domain\Space\Events\SpaceDeactivated;

class Space
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private string $name,
        private ?string $description,
        private SpaceType $type,
        private SpaceStatus $status,
        private int $capacity,
        private bool $requiresApproval,
        private ?int $maxDurationHours,
        private int $maxAdvanceDays,
        private int $minAdvanceHours,
        private int $cancellationDeadlineHours,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        string $name,
        ?string $description,
        SpaceType $type,
        int $capacity,
        bool $requiresApproval,
        ?int $maxDurationHours,
        int $maxAdvanceDays,
        int $minAdvanceHours,
        int $cancellationDeadlineHours,
    ): self {
        $space = new self(
            $id,
            $name,
            $description,
            $type,
            SpaceStatus::Active,
            $capacity,
            $requiresApproval,
            $maxDurationHours,
            $maxAdvanceDays,
            $minAdvanceHours,
            $cancellationDeadlineHours,
            new DateTimeImmutable,
        );

        $space->domainEvents[] = new SpaceCreated($id->value(), $name, $type->value);

        return $space;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function type(): SpaceType
    {
        return $this->type;
    }

    public function status(): SpaceStatus
    {
        return $this->status;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function requiresApproval(): bool
    {
        return $this->requiresApproval;
    }

    public function maxDurationHours(): ?int
    {
        return $this->maxDurationHours;
    }

    public function maxAdvanceDays(): int
    {
        return $this->maxAdvanceDays;
    }

    public function minAdvanceHours(): int
    {
        return $this->minAdvanceHours;
    }

    public function cancellationDeadlineHours(): int
    {
        return $this->cancellationDeadlineHours;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canAcceptReservations(): bool
    {
        return $this->status->canAcceptReservations();
    }

    public function activate(): void
    {
        $this->status = SpaceStatus::Active;
    }

    public function deactivate(): void
    {
        if ($this->status === SpaceStatus::Inactive) {
            return;
        }

        $this->status = SpaceStatus::Inactive;
        $this->domainEvents[] = new SpaceDeactivated($this->id->value());
    }

    public function setMaintenance(): void
    {
        $this->status = SpaceStatus::Maintenance;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function updateType(SpaceType $type): void
    {
        $this->type = $type;
    }

    public function updateCapacity(int $capacity): void
    {
        $this->capacity = $capacity;
    }

    public function updateRequiresApproval(bool $requiresApproval): void
    {
        $this->requiresApproval = $requiresApproval;
    }

    public function updateMaxDurationHours(?int $maxDurationHours): void
    {
        $this->maxDurationHours = $maxDurationHours;
    }

    public function updateMaxAdvanceDays(int $maxAdvanceDays): void
    {
        $this->maxAdvanceDays = $maxAdvanceDays;
    }

    public function updateMinAdvanceHours(int $minAdvanceHours): void
    {
        $this->minAdvanceHours = $minAdvanceHours;
    }

    public function updateCancellationDeadlineHours(int $cancellationDeadlineHours): void
    {
        $this->cancellationDeadlineHours = $cancellationDeadlineHours;
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
