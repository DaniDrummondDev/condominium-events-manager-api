<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListeners;

use App\Infrastructure\Cache\SpaceAvailabilityCache;
use Domain\Reservation\Events\ReservationRequested;

class InvalidateSpaceAvailabilityCache
{
    public function __construct(
        private readonly SpaceAvailabilityCache $cache,
    ) {}

    public function handle(ReservationRequested $event): void
    {
        $this->cache->invalidateRange(
            $event->spaceId,
            $event->startDatetime,
            $event->endDatetime,
        );
    }
}
