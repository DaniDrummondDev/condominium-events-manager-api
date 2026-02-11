<?php

declare(strict_types=1);

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\CreateSpaceDTO;
use Application\Space\DTOs\SpaceDTO;
use Application\Space\UseCases\CreateSpace;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;
use Domain\Space\Events\SpaceCreated;

afterEach(fn () => Mockery::close());

function createSpaceTenantContext(): TenantContext
{
    return new TenantContext(
        tenantId: Uuid::generate()->value(),
        tenantSlug: 'condo-test',
        tenantName: 'Condominio Teste',
        tenantType: 'vertical',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_test',
        resolvedAt: new DateTimeImmutable,
    );
}

test('creates space and returns SpaceDTO', function () {
    $tenantContext = createSpaceTenantContext();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('countActiveByTenant')->andReturn(0);
    $spaceRepo->shouldReceive('findByName')->andReturnNull();
    $spaceRepo->shouldReceive('save')->once();

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(10);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof SpaceCreated;
        });

    $useCase = new CreateSpace($spaceRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateSpaceDTO(
        name: 'Churrasqueira',
        description: 'Churrasqueira do bloco A',
        type: 'bbq',
        capacity: 30,
        requiresApproval: false,
        maxDurationHours: 4,
        maxAdvanceDays: 30,
        minAdvanceHours: 24,
        cancellationDeadlineHours: 12,
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SpaceDTO::class)
        ->and($result->name)->toBe('Churrasqueira')
        ->and($result->description)->toBe('Churrasqueira do bloco A')
        ->and($result->type)->toBe('bbq')
        ->and($result->status)->toBe('active')
        ->and($result->capacity)->toBe(30)
        ->and($result->requiresApproval)->toBeFalse()
        ->and($result->maxDurationHours)->toBe(4)
        ->and($result->maxAdvanceDays)->toBe(30)
        ->and($result->minAdvanceHours)->toBe(24)
        ->and($result->cancellationDeadlineHours)->toBe(12);
});

test('throws SPACE_LIMIT_REACHED when max_spaces limit reached', function () {
    $tenantContext = createSpaceTenantContext();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('countActiveByTenant')->andReturn(5);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(5);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateSpace($spaceRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateSpaceDTO(
        name: 'Piscina',
        description: null,
        type: 'pool',
        capacity: 50,
        requiresApproval: true,
        maxDurationHours: null,
        maxAdvanceDays: 14,
        minAdvanceHours: 48,
        cancellationDeadlineHours: 24,
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_LIMIT_REACHED')
            ->and($e->context())->toHaveKey('max_spaces', 5)
            ->and($e->context())->toHaveKey('current_count', 5);
    }
});

test('throws SPACE_NAME_DUPLICATE when name already exists', function () {
    $tenantContext = createSpaceTenantContext();

    $existingSpace = Space::create(
        Uuid::generate(),
        'Churrasqueira',
        'Descricao',
        SpaceType::Bbq,
        20,
        false,
        4,
        30,
        24,
        12,
    );

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('countActiveByTenant')->andReturn(1);
    $spaceRepo->shouldReceive('findByName')->andReturn($existingSpace);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(10);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateSpace($spaceRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateSpaceDTO(
        name: 'Churrasqueira',
        description: null,
        type: 'bbq',
        capacity: 30,
        requiresApproval: false,
        maxDurationHours: 4,
        maxAdvanceDays: 30,
        minAdvanceHours: 24,
        cancellationDeadlineHours: 12,
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NAME_DUPLICATE')
            ->and($e->context())->toHaveKey('name', 'Churrasqueira');
    }
});
