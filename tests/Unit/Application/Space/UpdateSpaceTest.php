<?php

declare(strict_types=1);

use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\DTOs\SpaceDTO;
use Application\Space\DTOs\UpdateSpaceDTO;
use Application\Space\UseCases\UpdateSpace;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Enums\SpaceType;

afterEach(fn () => Mockery::close());

test('updates space fields', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
        'Churrasqueira',
        'Descricao antiga',
        SpaceType::Bbq,
        20,
        false,
        4,
        30,
        24,
        12,
    );
    $space->pullDomainEvents();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);
    $spaceRepo->shouldReceive('findByName')->andReturnNull();
    $spaceRepo->shouldReceive('save')->once();

    $useCase = new UpdateSpace($spaceRepo);
    $dto = new UpdateSpaceDTO(
        spaceId: $spaceId->value(),
        name: 'Churrasqueira Nova',
        description: 'Descricao atualizada',
        capacity: 40,
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SpaceDTO::class)
        ->and($result->name)->toBe('Churrasqueira Nova')
        ->and($result->description)->toBe('Descricao atualizada')
        ->and($result->capacity)->toBe(40)
        ->and($result->type)->toBe('bbq')
        ->and($result->status)->toBe('active');
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceId = Uuid::generate();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new UpdateSpace($spaceRepo);
    $dto = new UpdateSpaceDTO(
        spaceId: $spaceId->value(),
        name: 'Piscina',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NOT_FOUND')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value());
    }
});

test('throws SPACE_NAME_DUPLICATE when name already taken by another space', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
        'Churrasqueira',
        null,
        SpaceType::Bbq,
        20,
        false,
        4,
        30,
        24,
        12,
    );
    $space->pullDomainEvents();

    $otherSpaceId = Uuid::generate();
    $otherSpace = Space::create(
        $otherSpaceId,
        'Piscina',
        null,
        SpaceType::Pool,
        50,
        true,
        null,
        14,
        48,
        24,
    );

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);
    $spaceRepo->shouldReceive('findByName')->with('Piscina')->andReturn($otherSpace);

    $useCase = new UpdateSpace($spaceRepo);
    $dto = new UpdateSpaceDTO(
        spaceId: $spaceId->value(),
        name: 'Piscina',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NAME_DUPLICATE')
            ->and($e->context())->toHaveKey('name', 'Piscina');
    }
});
