<?php

declare(strict_types=1);

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Billing\Contracts\FeatureResolverInterface;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\DTOs\CreateUnitDTO;
use Application\Unit\DTOs\UnitDTO;
use Application\Unit\UseCases\CreateUnit;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Block;
use Domain\Unit\Entities\Unit;
use Domain\Unit\Events\UnitCreated;

afterEach(fn () => Mockery::close());

function createUnitVerticalContext(): TenantContext
{
    return new TenantContext(
        tenantId: Uuid::generate()->value(),
        tenantSlug: 'condo-vertical',
        tenantName: 'Condominio Vertical',
        tenantType: 'vertical',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_vertical',
        resolvedAt: new DateTimeImmutable,
    );
}

function createUnitHorizontalContext(): TenantContext
{
    return new TenantContext(
        tenantId: Uuid::generate()->value(),
        tenantSlug: 'condo-horizontal',
        tenantName: 'Condominio Horizontal',
        tenantType: 'horizontal',
        tenantStatus: 'active',
        databaseName: 'tenant_condo_horizontal',
        resolvedAt: new DateTimeImmutable,
    );
}

test('creates unit for vertical condo with blockId', function () {
    $tenantContext = createUnitVerticalContext();
    $blockId = Uuid::generate();

    $block = Block::create($blockId, 'Bloco A', 'A', 10, $tenantContext->tenantId);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('countActiveByTenant')->andReturn(0);
    $unitRepo->shouldReceive('findByNumber')->andReturnNull();
    $unitRepo->shouldReceive('save')->once();

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findById')->andReturn($block);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(100);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CreateUnit($unitRepo, $blockRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateUnitDTO($blockId->value(), '101', 1, 'apartment');

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(UnitDTO::class)
        ->and($result->blockId)->toBe($blockId->value())
        ->and($result->number)->toBe('101')
        ->and($result->floor)->toBe(1)
        ->and($result->type)->toBe('apartment')
        ->and($result->status)->toBe('active')
        ->and($result->isOccupied)->toBeFalse();
});

test('throws UNIT_BLOCK_REQUIRED for vertical condo without blockId', function () {
    $tenantContext = createUnitVerticalContext();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateUnit($unitRepo, $blockRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateUnitDTO(null, '101', 1, 'apartment');

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_BLOCK_REQUIRED')
            ->and($e->context())->toHaveKey('condominium_type', 'vertical');
    }
});

test('throws BLOCK_NOT_FOUND when blockId does not exist', function () {
    $tenantContext = createUnitVerticalContext();
    $blockId = Uuid::generate();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findById')->andReturnNull();

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateUnit($unitRepo, $blockRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateUnitDTO($blockId->value(), '101', 1, 'apartment');

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('BLOCK_NOT_FOUND')
            ->and($e->context())->toHaveKey('block_id', $blockId->value());
    }
});

test('throws UNIT_LIMIT_REACHED when max_units feature exceeded', function () {
    $tenantContext = createUnitVerticalContext();
    $blockId = Uuid::generate();
    $block = Block::create($blockId, 'Bloco A', 'A', 10, $tenantContext->tenantId);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('countActiveByTenant')->andReturn(5);

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findById')->andReturn($block);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(5);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateUnit($unitRepo, $blockRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateUnitDTO($blockId->value(), '106', 1, 'apartment');

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_LIMIT_REACHED')
            ->and($e->context())->toHaveKey('max_units', 5)
            ->and($e->context())->toHaveKey('current_count', 5);
    }
});

test('throws UNIT_NUMBER_DUPLICATE when number already exists', function () {
    $tenantContext = createUnitVerticalContext();
    $blockId = Uuid::generate();
    $block = Block::create($blockId, 'Bloco A', 'A', 10, $tenantContext->tenantId);

    $existingUnit = Unit::create($blockId, $blockId, '101', 1, Domain\Unit\Enums\UnitType::Apartment);

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('countActiveByTenant')->andReturn(2);
    $unitRepo->shouldReceive('findByNumber')->andReturn($existingUnit);

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findById')->andReturn($block);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(100);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateUnit($unitRepo, $blockRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateUnitDTO($blockId->value(), '101', 1, 'apartment');

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('UNIT_NUMBER_DUPLICATE')
            ->and($e->context())->toHaveKey('number', '101');
    }
});

test('creates unit for horizontal condo with null blockId', function () {
    $tenantContext = createUnitHorizontalContext();

    $unitRepo = Mockery::mock(UnitRepositoryInterface::class);
    $unitRepo->shouldReceive('countActiveByTenant')->andReturn(0);
    $unitRepo->shouldReceive('findByNumber')->andReturnNull();
    $unitRepo->shouldReceive('save')->once();

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);

    $featureResolver = Mockery::mock(FeatureResolverInterface::class);
    $featureResolver->shouldReceive('featureLimit')->andReturn(50);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof UnitCreated;
        });

    $useCase = new CreateUnit($unitRepo, $blockRepo, $featureResolver, $eventDispatcher, $tenantContext);
    $dto = new CreateUnitDTO(null, 'Casa 1', null, 'house');

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(UnitDTO::class)
        ->and($result->blockId)->toBeNull()
        ->and($result->number)->toBe('Casa 1')
        ->and($result->floor)->toBeNull()
        ->and($result->type)->toBe('house')
        ->and($result->status)->toBe('active')
        ->and($result->isOccupied)->toBeFalse();
});
