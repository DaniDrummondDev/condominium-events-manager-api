<?php

declare(strict_types=1);

use App\Infrastructure\MultiTenancy\TenantContext;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\DTOs\BlockDTO;
use Application\Unit\DTOs\CreateBlockDTO;
use Application\Unit\UseCases\CreateBlock;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Unit\Entities\Block;
use Domain\Unit\Events\BlockCreated;

afterEach(fn () => Mockery::close());

function buildVerticalTenantContext(): TenantContext
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

function buildHorizontalTenantContext(): TenantContext
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

test('creates block for vertical condo and returns BlockDTO', function () {
    $tenantContext = buildVerticalTenantContext();

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findByIdentifier')->with('A')->andReturnNull();
    $blockRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CreateBlock($blockRepo, $eventDispatcher, $tenantContext);
    $dto = new CreateBlockDTO('Bloco A', 'A', 10);

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(BlockDTO::class)
        ->and($result->name)->toBe('Bloco A')
        ->and($result->identifier)->toBe('A')
        ->and($result->floors)->toBe(10)
        ->and($result->status)->toBe('active');
});

test('throws BLOCK_NOT_SUPPORTED for horizontal condo type', function () {
    $tenantContext = buildHorizontalTenantContext();

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateBlock($blockRepo, $eventDispatcher, $tenantContext);
    $dto = new CreateBlockDTO('Bloco A', 'A', 5);

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('BLOCK_NOT_SUPPORTED')
            ->and($e->context())->toHaveKey('condominium_type', 'horizontal');
    }
});

test('throws BLOCK_IDENTIFIER_DUPLICATE when identifier already exists', function () {
    $tenantContext = buildVerticalTenantContext();

    $existingBlock = Block::create(
        Uuid::generate(),
        'Bloco A',
        'A',
        10,
        $tenantContext->tenantId,
    );

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findByIdentifier')->with('A')->andReturn($existingBlock);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CreateBlock($blockRepo, $eventDispatcher, $tenantContext);
    $dto = new CreateBlockDTO('Bloco A Novo', 'A', 8);

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('BLOCK_IDENTIFIER_DUPLICATE')
            ->and($e->context())->toHaveKey('identifier', 'A');
    }
});

test('calls save on repository and dispatchAll on event dispatcher', function () {
    $tenantContext = buildVerticalTenantContext();

    $blockRepo = Mockery::mock(BlockRepositoryInterface::class);
    $blockRepo->shouldReceive('findByIdentifier')->with('B')->andReturnNull();
    $blockRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn (Block $block) => $block->identifier() === 'B'
            && $block->name() === 'Bloco B',
        );

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->withArgs(function (array $events) {
            return count($events) === 1 && $events[0] instanceof BlockCreated;
        });

    $useCase = new CreateBlock($blockRepo, $eventDispatcher, $tenantContext);
    $dto = new CreateBlockDTO('Bloco B', 'B', 5);

    $useCase->execute($dto);
});
