<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use DateTimeImmutable;
use Domain\Shared\Exceptions\DomainException;

final readonly class DateRange implements \Stringable
{
    public function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {
        if ($start >= $end) {
            throw new DomainException(
                'Start date must be before end date',
                'INVALID_DATE_RANGE',
                [
                    'start' => $start->format('Y-m-d H:i:s'),
                    'end' => $end->format('Y-m-d H:i:s'),
                ],
            );
        }
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function contains(DateTimeImmutable $dateTime): bool
    {
        return $dateTime >= $this->start && $dateTime < $this->end;
    }

    public function durationInMinutes(): int
    {
        return (int) (($this->end->getTimestamp() - $this->start->getTimestamp()) / 60);
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s — %s',
            $this->start->format('Y-m-d H:i'),
            $this->end->format('Y-m-d H:i'),
        );
    }
}
