<?php

declare(strict_types=1);

use Domain\People\Events\ServiceProviderCheckedIn;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ServiceProviderCheckedIn implements DomainEvent', function () {
    $event = new ServiceProviderCheckedIn('visit-id', 'provider-id', 'unit-id', 'user-id');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ServiceProviderCheckedIn has correct eventName', function () {
    $event = new ServiceProviderCheckedIn('visit-id', 'provider-id', 'unit-id', 'user-id');

    expect($event->eventName())->toBe('service_provider.checked_in');
});

test('ServiceProviderCheckedIn aggregateId matches visitId', function () {
    $id = Uuid::generate()->value();
    $event = new ServiceProviderCheckedIn($id, 'provider-id', 'unit-id', 'user-id');

    expect($event->aggregateId()->value())->toBe($id);
});

test('ServiceProviderCheckedIn occurredAt is set', function () {
    $event = new ServiceProviderCheckedIn('visit-id', 'provider-id', 'unit-id', 'user-id');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ServiceProviderCheckedIn payload contains all fields', function () {
    $event = new ServiceProviderCheckedIn('v-id', 'p-id', 'u-id', 'by-id');

    expect($event->payload())->toBe([
        'visit_id' => 'v-id',
        'service_provider_id' => 'p-id',
        'unit_id' => 'u-id',
        'checked_in_by' => 'by-id',
    ]);
});
