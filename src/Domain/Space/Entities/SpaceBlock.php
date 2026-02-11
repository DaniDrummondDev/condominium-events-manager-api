<?php

declare(strict_types=1);

namespace Domain\Space\Entities;

use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class SpaceBlock
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $spaceId,
        private readonly string $reason,
        private readonly DateTimeImmutable $startDatetime,
        private readonly DateTimeImmutable $endDatetime,
        private readonly Uuid $blockedBy,
        private readonly ?string $notes,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $spaceId,
        string $reason,
        DateTimeImmutable $startDatetime,
        DateTimeImmutable $endDatetime,
        Uuid $blockedBy,
        ?string $notes,
    ): self {
        if ($endDatetime <= $startDatetime) {
            throw new DomainException(
                'Block end datetime must be after start datetime',
                'SPACE_BLOCK_INVALID_PERIOD',
                [
                    'space_id' => $spaceId->value(),
                    'start_datetime' => $startDatetime->format('c'),
                    'end_datetime' => $endDatetime->format('c'),
                ],
            );
        }

        return new self($id, $spaceId, $reason, $startDatetime, $endDatetime, $blockedBy, $notes, new DateTimeImmutable);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function spaceId(): Uuid
    {
        return $this->spaceId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function startDatetime(): DateTimeImmutable
    {
        return $this->startDatetime;
    }

    public function endDatetime(): DateTimeImmutable
    {
        return $this->endDatetime;
    }

    public function blockedBy(): Uuid
    {
        return $this->blockedBy;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
