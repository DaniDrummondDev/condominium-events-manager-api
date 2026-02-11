<?php

declare(strict_types=1);

use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\DTOs\RuleDTO;
use Application\Governance\DTOs\UpdateRuleDTO;
use Application\Governance\UseCases\UpdateRule;
use Domain\Governance\Entities\CondominiumRule;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createTestRule(): CondominiumRule
{
    return CondominiumRule::create(
        id: Uuid::generate(),
        title: 'Original Title',
        description: 'Original description',
        category: 'general',
        order: 1,
        createdBy: Uuid::generate(),
    );
}

test('updates rule successfully', function () {
    $rule = createTestRule();

    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->once()->andReturn($rule);
    $ruleRepo->shouldReceive('save')->once();

    $useCase = new UpdateRule($ruleRepo);

    $dto = new UpdateRuleDTO(
        ruleId: $rule->id()->value(),
        title: 'Updated Title',
        description: 'Updated description',
        category: 'noise',
        order: 5,
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(RuleDTO::class)
        ->and($result->title)->toBe('Updated Title')
        ->and($result->description)->toBe('Updated description')
        ->and($result->category)->toBe('noise')
        ->and($result->order)->toBe(5);
});

test('throws RULE_NOT_FOUND when rule does not exist', function () {
    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->once()->andReturnNull();

    $useCase = new UpdateRule($ruleRepo);

    $dto = new UpdateRuleDTO(
        ruleId: Uuid::generate()->value(),
        title: 'Updated Title',
        description: null,
        category: null,
        order: null,
    );

    $useCase->execute($dto);
})->throws(DomainException::class, 'Rule not found');

test('only updates provided fields and leaves others unchanged', function () {
    $rule = createTestRule();

    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->once()->andReturn($rule);
    $ruleRepo->shouldReceive('save')->once();

    $useCase = new UpdateRule($ruleRepo);

    $dto = new UpdateRuleDTO(
        ruleId: $rule->id()->value(),
        title: 'Only Title Changed',
        description: null,
        category: null,
        order: null,
    );

    $result = $useCase->execute($dto);

    expect($result->title)->toBe('Only Title Changed')
        ->and($result->description)->toBe('Original description')
        ->and($result->category)->toBe('general')
        ->and($result->order)->toBe(1);
});
