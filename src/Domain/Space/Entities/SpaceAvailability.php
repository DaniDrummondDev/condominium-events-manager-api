<?php

declare(strict_types=1);

namespace Domain\Space\Entities;

use Domain\Shared\ValueObjects\Uuid;

class SpaceAvailability
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $spaceId,
        private int $dayOfWeek,
        private string $startTime,
        private string $endTime,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $spaceId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
    ): self {
        return new self($id, $spaceId, $dayOfWeek, $startTime, $endTime);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function spaceId(): Uuid
    {
        return $this->spaceId;
    }

    public function dayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function startTime(): string
    {
        return $this->startTime;
    }

    public function endTime(): string
    {
        return $this->endTime;
    }

    public function overlaps(self $other): bool
    {
        if ($this->dayOfWeek !== $other->dayOfWeek) {
            return false;
        }

        return $this->startTime < $other->endTime && $other->startTime < $this->endTime;
    }

    public function updateTimes(string $startTime, string $endTime): void
    {
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }
}
