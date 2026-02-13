<?php

declare(strict_types=1);

use App\Infrastructure\Cache\SpaceAvailabilityCache;
use Illuminate\Support\Facades\Cache;

test('put stores and get retrieves data for a space and date', function (): void {
    $cache = new SpaceAvailabilityCache;
    $spaceId = 'space-123';
    $date = '2026-02-15';
    $slots = [
        ['start' => '09:00', 'end' => '10:00', 'available' => true],
        ['start' => '10:00', 'end' => '11:00', 'available' => false],
    ];

    $cache->put($spaceId, $date, $slots);
    $retrieved = $cache->get($spaceId, $date);

    expect($retrieved)->toBe($slots);
});

test('get returns null when data does not exist', function (): void {
    $cache = new SpaceAvailabilityCache;

    $result = $cache->get('nonexistent-space', '2026-02-15');

    expect($result)->toBeNull();
});

test('invalidate removes cached data for a space and date', function (): void {
    $cache = new SpaceAvailabilityCache;
    $spaceId = 'space-456';
    $date = '2026-02-16';
    $slots = [['start' => '14:00', 'end' => '15:00', 'available' => true]];

    $cache->put($spaceId, $date, $slots);
    expect($cache->get($spaceId, $date))->toBe($slots);

    $cache->invalidate($spaceId, $date);

    expect($cache->get($spaceId, $date))->toBeNull();
});

test('invalidateRange clears all dates in range', function (): void {
    $cache = new SpaceAvailabilityCache;
    $spaceId = 'space-789';
    $slots = [['start' => '09:00', 'end' => '10:00', 'available' => true]];

    // Store data for multiple dates
    $cache->put($spaceId, '2026-02-20', $slots);
    $cache->put($spaceId, '2026-02-21', $slots);
    $cache->put($spaceId, '2026-02-22', $slots);
    $cache->put($spaceId, '2026-02-23', $slots);

    // Verify data exists
    expect($cache->get($spaceId, '2026-02-20'))->toBe($slots)
        ->and($cache->get($spaceId, '2026-02-21'))->toBe($slots)
        ->and($cache->get($spaceId, '2026-02-22'))->toBe($slots)
        ->and($cache->get($spaceId, '2026-02-23'))->toBe($slots);

    // Invalidate range (Feb 20-22)
    $cache->invalidateRange($spaceId, '2026-02-20 10:00:00', '2026-02-22 14:00:00');

    // Verify range is cleared
    expect($cache->get($spaceId, '2026-02-20'))->toBeNull()
        ->and($cache->get($spaceId, '2026-02-21'))->toBeNull()
        ->and($cache->get($spaceId, '2026-02-22'))->toBeNull()
        // Feb 23 should still exist (outside range)
        ->and($cache->get($spaceId, '2026-02-23'))->toBe($slots);
});

test('invalidateRange handles single day range', function (): void {
    $cache = new SpaceAvailabilityCache;
    $spaceId = 'space-single';
    $slots = [['start' => '12:00', 'end' => '13:00', 'available' => true]];

    $cache->put($spaceId, '2026-02-25', $slots);

    // Same start and end date
    $cache->invalidateRange($spaceId, '2026-02-25 09:00:00', '2026-02-25 18:00:00');

    expect($cache->get($spaceId, '2026-02-25'))->toBeNull();
});

test('cache uses correct key format', function (): void {
    Cache::spy();

    $cache = new SpaceAvailabilityCache;
    $spaceId = 'space-key-test';
    $date = '2026-03-01 15:30:00';
    $slots = [['available' => true]];

    $cache->put($spaceId, $date, $slots);

    Cache::shouldHaveReceived('put')
        ->once()
        ->with('space:availability:space-key-test:2026-03-01', $slots, 300);
});
