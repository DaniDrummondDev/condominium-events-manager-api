<?php

declare(strict_types=1);

use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Application\Tenant\DTOs\CreateTenantDTO;
use Application\Tenant\UseCases\ProvisionTenant;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Entities\Tenant;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;
use Domain\Tenant\Events\TenantCreated;

function mockRepository(?Tenant $findBySlugResult = null): TenantRepositoryInterface
{
    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findBySlug')->andReturn($findBySlugResult);
    $repo->shouldReceive('save')->andReturnNull();

    return $repo;
}

function mockEventDispatcher(): EventDispatcherInterface
{
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatch')->andReturnNull();

    return $dispatcher;
}

test('provisions a new tenant successfully', function () {
    $repo = mockRepository(null);
    $dispatcher = mockEventDispatcher();

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('condo-verde', 'Condomínio Verde', 'vertical');

    $tenant = $useCase->execute($dto);

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->slug())->toBe('condo-verde')
        ->and($tenant->name())->toBe('Condomínio Verde')
        ->and($tenant->type())->toBe(CondominiumType::Vertical)
        ->and($tenant->status())->toBe(TenantStatus::Provisioning)
        ->and($tenant->databaseName())->toBe('tenant_condo-verde');
});

test('dispatches TenantCreated event on provision', function () {
    $repo = mockRepository(null);

    $dispatcher = Mockery::mock(EventDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->withArgs(fn ($event) => $event instanceof TenantCreated
            && $event->eventName() === 'tenant.created'
            && $event->payload()['slug'] === 'condo-teste',
        );

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('condo-teste', 'Condomínio Teste', 'horizontal');

    $useCase->execute($dto);
});

test('saves tenant to repository', function () {
    $repo = Mockery::mock(TenantRepositoryInterface::class);
    $repo->shouldReceive('findBySlug')->andReturnNull();
    $repo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Tenant $t) => $t->slug() === 'condo-save'
            && $t->status() === TenantStatus::Provisioning,
        );

    $dispatcher = mockEventDispatcher();

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('condo-save', 'Condomínio Save', 'mixed');

    $useCase->execute($dto);
});

test('throws when slug already exists', function () {
    $existingTenant = Tenant::create(
        Uuid::generate(),
        'condo-existente',
        'Condomínio Existente',
        CondominiumType::Vertical,
    );

    $repo = mockRepository($existingTenant);
    $dispatcher = mockEventDispatcher();

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('condo-existente', 'Outro Nome', 'vertical');

    $useCase->execute($dto);
})->throws(DomainException::class, "Tenant with slug 'condo-existente' already exists");

test('throws on invalid condominium type', function () {
    $repo = mockRepository(null);
    $dispatcher = mockEventDispatcher();

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('condo-novo', 'Condomínio Novo', 'invalid_type');

    $useCase->execute($dto);
})->throws(DomainException::class, "Invalid condominium type: 'invalid_type'");

test('slug duplicate throws with correct error code', function () {
    $existingTenant = Tenant::create(
        Uuid::generate(),
        'dup-slug',
        'Duplicado',
        CondominiumType::Horizontal,
    );

    $repo = mockRepository($existingTenant);
    $dispatcher = mockEventDispatcher();

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('dup-slug', 'Outro', 'horizontal');

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('TENANT_SLUG_ALREADY_EXISTS')
            ->and($e->context())->toHaveKey('slug', 'dup-slug');
    }
});

test('invalid type throws with correct error code', function () {
    $repo = mockRepository(null);
    $dispatcher = mockEventDispatcher();

    $useCase = new ProvisionTenant($repo, $dispatcher);
    $dto = new CreateTenantDTO('condo-x', 'Condomínio X', 'townhouse');

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('INVALID_CONDOMINIUM_TYPE')
            ->and($e->context())->toHaveKey('type', 'townhouse')
            ->and($e->context())->toHaveKey('allowed');
    }
});
