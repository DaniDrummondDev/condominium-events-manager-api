<?php

declare(strict_types=1);

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

test('generates a valid UUIDv7', function () {
    $uuid = Uuid::generate();

    expect($uuid->value())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

test('creates from valid string', function () {
    $uuid = Uuid::fromString('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');

    expect($uuid->value())->toBe('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');
});

test('normalizes to lowercase', function () {
    $uuid = Uuid::fromString('01934B6E-3A45-7F8C-8D2E-1A2B3C4D5E6F');

    expect($uuid->value())->toBe('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');
});

test('throws on invalid UUID', function () {
    Uuid::fromString('not-a-uuid');
})->throws(DomainException::class, 'Invalid UUID');

test('equals works correctly', function () {
    $uuid1 = Uuid::fromString('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');
    $uuid2 = Uuid::fromString('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');
    $uuid3 = Uuid::generate();

    expect($uuid1->equals($uuid2))->toBeTrue()
        ->and($uuid1->equals($uuid3))->toBeFalse();
});

test('converts to string', function () {
    $uuid = Uuid::fromString('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');

    expect((string) $uuid)->toBe('01934b6e-3a45-7f8c-8d2e-1a2b3c4d5e6f');
});

test('generated UUIDs are unique', function () {
    $uuids = array_map(fn () => Uuid::generate()->value(), range(1, 100));

    expect(array_unique($uuids))->toHaveCount(100);
});
