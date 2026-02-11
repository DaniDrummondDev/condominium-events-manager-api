<?php

declare(strict_types=1);

namespace Domain\Unit\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Enums\BlockStatus;
use Domain\Unit\Events\BlockCreated;

class Block
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private string $name,
        private string $identifier,
        private ?int $floors,
        private BlockStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        string $name,
        string $identifier,
        ?int $floors,
        string $tenantId,
    ): self {
        $block = new self($id, $name, $identifier, $floors, BlockStatus::Active, new DateTimeImmutable);

        $block->domainEvents[] = new BlockCreated($id->value(), $tenantId);

        return $block;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function floors(): ?int
    {
        return $this->floors;
    }

    public function status(): BlockStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function updateIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function updateFloors(?int $floors): void
    {
        $this->floors = $floors;
    }

    public function activate(): void
    {
        $this->status = BlockStatus::Active;
    }

    public function deactivate(): void
    {
        if ($this->status === BlockStatus::Inactive) {
            return;
        }

        $this->status = BlockStatus::Inactive;
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
