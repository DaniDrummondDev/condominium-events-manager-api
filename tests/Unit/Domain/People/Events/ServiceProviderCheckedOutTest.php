<?php

declare(strict_types=1);

use Domain\People\Events\ServiceProviderCheckedOut;
use Domain\Shared\Events\DomainEvent;
use Domain\Shared\ValueObjects\Uuid;

test('ServiceProviderCheckedOut implements DomainEvent', function () {
    $event = new ServiceProviderCheckedOut('visit-id', 'provider-id', 'unit-id', 'user-id');

    expect($event)->toBeInstanceOf(DomainEvent::class);
});

test('ServiceProviderCheckedOut has correct eventName', function () {
    $event = new ServiceProviderCheckedOut('visit-id', 'provider-id', 'unit-id', 'user-id');

    expect($event->eventName())->toBe('service_provider.checked_out');
});

test('ServiceProviderCheckedOut aggregateId matches visitId', function () {
    $id = Uuid::generate()->value();
    $event = new ServiceProviderCheckedOut($id, 'provider-id', 'unit-id', 'user-id');

    expect($event->aggregateId()->value())->toBe($id);
});

test('ServiceProviderCheckedOut occurredAt is set', function () {
    $event = new ServiceProviderCheckedOut('visit-id', 'provider-id', 'unit-id', 'user-id');

    expect($event->occurredAt())->toBeInstanceOf(DateTimeImmutable::class);
});

test('ServiceProviderCheckedOut payload contains all fields', function () {
    $event = new ServiceProviderCheckedOut('v-id', 'p-id', 'u-id', 'by-id');

    expect($event->payload())->toBe([
        'visit_id' => 'v-id',
        'service_provider_id' => 'p-id',
        'unit_id' => 'u-id',
        'checked_out_by' => 'by-id',
    ]);
});
