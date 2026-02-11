<?php

declare(strict_types=1);

use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Application\Space\DTOs\ConfigureSpaceRuleDTO;
use Application\Space\DTOs\SpaceRuleDTO;
use Application\Space\UseCases\ConfigureSpaceRules;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\Space;
use Domain\Space\Entities\SpaceRule;
use Domain\Space\Enums\SpaceType;

afterEach(fn () => Mockery::close());

test('creates new rule', function () {
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

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturnNull();
    $ruleRepo->shouldReceive('save')->once();

    $useCase = new ConfigureSpaceRules($spaceRepo, $ruleRepo);
    $dto = new ConfigureSpaceRuleDTO(
        spaceId: $spaceId->value(),
        ruleKey: 'max_guests',
        ruleValue: '30',
        description: 'Maximo de convidados permitidos',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SpaceRuleDTO::class)
        ->and($result->spaceId)->toBe($spaceId->value())
        ->and($result->ruleKey)->toBe('max_guests')
        ->and($result->ruleValue)->toBe('30')
        ->and($result->description)->toBe('Maximo de convidados permitidos');
});

test('updates existing rule (upsert)', function () {
    $spaceId = Uuid::generate();
    $space = Space::create(
        $spaceId,
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
    $space->pullDomainEvents();

    $existingRule = SpaceRule::create(
        Uuid::generate(),
        $spaceId,
        'max_guests',
        '20',
        'Descricao antiga',
    );

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturn($space);

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findBySpaceIdAndKey')->andReturn($existingRule);
    $ruleRepo->shouldReceive('save')->once();

    $useCase = new ConfigureSpaceRules($spaceRepo, $ruleRepo);
    $dto = new ConfigureSpaceRuleDTO(
        spaceId: $spaceId->value(),
        ruleKey: 'max_guests',
        ruleValue: '50',
        description: 'Descricao atualizada',
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SpaceRuleDTO::class)
        ->and($result->ruleKey)->toBe('max_guests')
        ->and($result->ruleValue)->toBe('50')
        ->and($result->description)->toBe('Descricao atualizada');
});

test('throws SPACE_NOT_FOUND when space does not exist', function () {
    $spaceId = Uuid::generate();

    $spaceRepo = Mockery::mock(SpaceRepositoryInterface::class);
    $spaceRepo->shouldReceive('findById')->andReturnNull();

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);

    $useCase = new ConfigureSpaceRules($spaceRepo, $ruleRepo);
    $dto = new ConfigureSpaceRuleDTO(
        spaceId: $spaceId->value(),
        ruleKey: 'max_guests',
        ruleValue: '30',
    );

    try {
        $useCase->execute($dto);
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_NOT_FOUND')
            ->and($e->context())->toHaveKey('space_id', $spaceId->value());
    }
});
