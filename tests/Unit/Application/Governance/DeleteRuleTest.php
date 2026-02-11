<?php

declare(strict_types=1);

use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\UseCases\DeleteRule;
use Domain\Governance\Entities\CondominiumRule;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('deletes rule successfully', function () {
    $rule = CondominiumRule::create(
        id: Uuid::generate(),
        title: 'Test Rule',
        description: 'Test description',
        category: 'general',
        order: 1,
        createdBy: Uuid::generate(),
    );

    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->once()->andReturn($rule);
    $ruleRepo->shouldReceive('delete')->once()->with(Mockery::on(fn ($id) => $id->value() === $rule->id()->value()));

    $useCase = new DeleteRule($ruleRepo);
    $useCase->execute($rule->id()->value());
});

test('throws RULE_NOT_FOUND when rule does not exist', function () {
    $ruleRepo = Mockery::mock(CondominiumRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->once()->andReturnNull();

    $useCase = new DeleteRule($ruleRepo);

    $useCase->execute(Uuid::generate()->value());
})->throws(DomainException::class, 'Rule not found');
