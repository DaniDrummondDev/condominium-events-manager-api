<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

class SpaceAvailabilityCache
{
    private const int CACHE_TTL_SECONDS = 300;

    /**
     * @return array<mixed>|null
     */
    public function get(string $spaceId, string $date): ?array
    {
        return Cache::get($this->buildKey($spaceId, $date));
    }

    /**
     * @param array<mixed> $slots
     */
    public function put(string $spaceId, string $date, array $slots): void
    {
        Cache::put($this->buildKey($spaceId, $date), $slots, self::CACHE_TTL_SECONDS);
    }

    public function invalidate(string $spaceId, string $date): void
    {
        Cache::forget($this->buildKey($spaceId, $date));
    }

    public function invalidateRange(string $spaceId, string $startDatetime, string $endDatetime): void
    {
        $start = new DateTimeImmutable($startDatetime);
        $end = new DateTimeImmutable($endDatetime);

        $current = $start;

        while ($current <= $end) {
            $this->invalidate($spaceId, $current->format('Y-m-d'));
            $current = $current->modify('+1 day');
        }
    }

    private function buildKey(string $spaceId, string $date): string
    {
        $dateOnly = (new DateTimeImmutable($date))->format('Y-m-d');

        return "space:availability:{$spaceId}:{$dateOnly}";
    }
}
