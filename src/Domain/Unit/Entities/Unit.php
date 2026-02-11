<?php

declare(strict_types=1);

namespace Domain\Unit\Entities;

use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Enums\UnitStatus;
use Domain\Unit\Enums\UnitType;
use Domain\Unit\Events\UnitCreated;
use Domain\Unit\Events\UnitDeactivated;

class Unit
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly ?Uuid $blockId,
        private string $number,
        private ?int $floor,
        private UnitType $type,
        private UnitStatus $status,
        private bool $isOccupied,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        ?Uuid $blockId,
        string $number,
        ?int $floor,
        UnitType $type,
    ): self {
        $unit = new self($id, $blockId, $number, $floor, $type, UnitStatus::Active, false, new DateTimeImmutable);

        $unit->domainEvents[] = new UnitCreated($id->value(), $blockId?->value());

        return $unit;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function blockId(): ?Uuid
    {
        return $this->blockId;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function floor(): ?int
    {
        return $this->floor;
    }

    public function type(): UnitType
    {
        return $this->type;
    }

    public function status(): UnitStatus
    {
        return $this->status;
    }

    public function isOccupied(): bool
    {
        return $this->isOccupied;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateNumber(string $number): void
    {
        $this->number = $number;
    }

    public function updateFloor(?int $floor): void
    {
        $this->floor = $floor;
    }

    public function updateType(UnitType $type): void
    {
        $this->type = $type;
    }

    public function activate(): void
    {
        $this->status = UnitStatus::Active;
    }

    public function deactivate(): void
    {
        if ($this->status === UnitStatus::Inactive) {
            return;
        }

        $this->status = UnitStatus::Inactive;
        $this->domainEvents[] = new UnitDeactivated($this->id->value());
    }

    public function markOccupied(): void
    {
        if (! $this->status->isActive()) {
            throw new DomainException(
                'Cannot mark inactive unit as occupied',
                'UNIT_INACTIVE',
                ['unit_id' => $this->id->value()],
            );
        }

        $this->isOccupied = true;
    }

    public function markVacant(): void
    {
        $this->isOccupied = false;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
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
