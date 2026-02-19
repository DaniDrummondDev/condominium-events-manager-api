<?php

declare(strict_types=1);

use App\Infrastructure\EventListeners\DispatchProvisionTenantJob;
use App\Infrastructure\Jobs\Tenant\ProvisionTenantJob;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Events\TenantCreated;
use Illuminate\Support\Facades\Queue;

test('dispatches ProvisionTenantJob when TenantCreated event is handled', function (): void {
    Queue::fake();

    $tenantId = Uuid::generate();
    $event = new TenantCreated(
        tenantId: $tenantId,
        slug: 'condo-novo',
        name: 'Condomínio Novo',
        type: 'vertical',
        occurredAt: new DateTimeImmutable,
    );

    $listener = new DispatchProvisionTenantJob;
    $listener->handle($event);

    Queue::assertPushed(ProvisionTenantJob::class, function (ProvisionTenantJob $job) use ($tenantId) {
        return $job->tenantId === $tenantId->value();
    });
});

test('dispatches job with correct tenant ID', function (): void {
    Queue::fake();

    $tenantId = Uuid::generate();
    $event = new TenantCreated(
        tenantId: $tenantId,
        slug: 'condo-alpha',
        name: 'Alpha',
        type: 'horizontal',
        occurredAt: new DateTimeImmutable,
    );

    $listener = new DispatchProvisionTenantJob;
    $listener->handle($event);

    Queue::assertPushed(ProvisionTenantJob::class, 1);
});
