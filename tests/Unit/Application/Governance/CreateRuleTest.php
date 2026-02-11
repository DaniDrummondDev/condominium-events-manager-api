<?php

declare(strict_types=1);

use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\DTOs\CreateRuleDTO;
use Application\Governance\DTOs\RuleDTO;
use Application\Governance\UseCases\CreateRule;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('creates rule successfully and returns RuleDTO', function () {
    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('save')->once();

    $useCase = new CreateRule($ruleRepo);

    $dto = new CreateRuleDTO(
        title: 'No loud music after 10pm',
        description: 'Residents must keep noise levels down after 10pm',
        category: 'noise',
        order: 1,
        createdBy: Uuid::generate()->value(),
    );

    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(RuleDTO::class)
        ->and($result->title)->toBe('No loud music after 10pm')
        ->and($result->description)->toBe('Residents must keep noise levels down after 10pm')
        ->and($result->category)->toBe('noise')
        ->and($result->order)->toBe(1)
        ->and($result->isActive)->toBeTrue();
});

test('RuleDTO has correct fields', function () {
    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('save')->once();

    $createdBy = Uuid::generate()->value();
    $useCase = new CreateRule($ruleRepo);

    $dto = new CreateRuleDTO(
        title: 'Pool hours',
        description: 'Pool is open from 8am to 8pm',
        category: 'facilities',
        order: 2,
        createdBy: $createdBy,
    );

    $result = $useCase->execute($dto);

    expect($result->id)->toBeString()->not->toBeEmpty()
        ->and($result->title)->toBe('Pool hours')
        ->and($result->description)->toBe('Pool is open from 8am to 8pm')
        ->and($result->category)->toBe('facilities')
        ->and($result->isActive)->toBeBool()
        ->and($result->order)->toBe(2)
        ->and($result->createdBy)->toBe($createdBy)
        ->and($result->createdAt)->toBeString()->not->toBeEmpty();
});

test('saves rule to repository', function () {
    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('save')
        ->once()
        ->withArgs(fn ($rule) => $rule->title() === 'Test Rule');

    $useCase = new CreateRule($ruleRepo);

    $dto = new CreateRuleDTO(
        title: 'Test Rule',
        description: 'Test description',
        category: 'general',
        order: 1,
        createdBy: Uuid::generate()->value(),
    );

    $useCase->execute($dto);
});
