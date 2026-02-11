<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Application\Tenant\UseCases\SuspendTenant;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;
use Domain\Tenant\Events\TenantSuspended;

test('suspends an active tenant successfully', function () {
    $tenantId = Uuid::generate();
    $tenant = new Tenant(
        id: $tenantId,
        slug: 'condo-ativo',
        name: 'Condomínio Ativo',
        type: CondominiumType::Vertical,
        status: TenantStatus::Active,
        databaseName: 'tenant_condo_ativo',
    );

    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findById')->with($tenantId)->andReturn($tenant);
    $repo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Tenant $t) => $t->status() === TenantStatus::Suspended);

    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->withArgs(fn ($event) => $event instanceof TenantSuspended
            && $event->eventName() === 'tenant.suspended'
            && $event->payload()['reason'] === 'Pagamento atrasado',
        );

    $useCase = new SuspendTenant($repo, $dispatcher);
    $result = $useCase->execute($tenantId, 'Pagamento atrasado');

    expect($result->status())->toBe(TenantStatus::Suspended);
});

test('throws when tenant not found', function () {
    $tenantId = Uuid::generate();

    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findById')->with($tenantId)->andReturnNull();

    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new SuspendTenant($repo, $dispatcher);

    $useCase->execute($tenantId, 'Any reason');
})->throws(DomainException::class, 'Tenant not found');

test('tenant not found throws with correct error code', function () {
    $tenantId = Uuid::generate();

    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findById')->with($tenantId)->andReturnNull();

    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new SuspendTenant($repo, $dispatcher);

    try {
        $useCase->execute($tenantId, 'Motivo');
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('TENANT_NOT_FOUND')
            ->and($e->context())->toHaveKey('tenant_id', $tenantId->value());
    }
});

test('throws when trying to suspend a non-suspendable tenant', function () {
    $tenantId = Uuid::generate();
    $tenant = new Tenant(
        id: $tenantId,
        slug: 'condo-prospect',
        name: 'Condomínio Prospect',
        type: CondominiumType::Horizontal,
        status: TenantStatus::Prospect,
    );

    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findById')->with($tenantId)->andReturn($tenant);

    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new SuspendTenant($repo, $dispatcher);

    $useCase->execute($tenantId, 'Motivo');
})->throws(DomainException::class);

test('does not save or dispatch when suspension fails', function () {
    $tenantId = Uuid::generate();
    $tenant = new Tenant(
        id: $tenantId,
        slug: 'condo-archived',
        name: 'Condomínio Archived',
        type: CondominiumType::Mixed,
        status: TenantStatus::Archived,
    );

    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findById')->with($tenantId)->andReturn($tenant);
    $repo->shouldNotReceive('save');

    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatch');

    $useCase = new SuspendTenant($repo, $dispatcher);

    try {
        $useCase->execute($tenantId, 'Motivo');
    } catch (DomainException) {
        // Expected
    }
});
