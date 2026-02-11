<?php

declare(strict_types=1);

use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Application\Space\UseCases\DeleteSpaceRule;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Space\Entities\SpaceRule;

afterEach(fn () => Mockery::close());

test('deletes rule', function () {
    $ruleId = Uuid::generate();
    $spaceId = Uuid::generate();

    $rule = SpaceRule::create(
        $ruleId,
        $spaceId,
        'max_guests',
        '30',
        'Maximo de convidados',
    );

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->andReturn($rule);
    $ruleRepo->shouldReceive('delete')->once();

    $useCase = new DeleteSpaceRule($ruleRepo);
    $useCase->execute($ruleId->value());
});

test('throws SPACE_RULE_NOT_FOUND when not found', function () {
    $ruleId = Uuid::generate();

    $ruleRepo = Mockery::mock(SpaceRuleRepositoryInterface::class);
    $ruleRepo->shouldReceive('findById')->andReturnNull();

    $useCase = new DeleteSpaceRule($ruleRepo);

    try {
        $useCase->execute($ruleId->value());
        $this->fail('Expected DomainException');
    } catch (DomainException $e) {
        expect($e->errorCode())->toBe('SPACE_RULE_NOT_FOUND')
            ->and($e->context())->toHaveKey('rule_id', $ruleId->value());
    }
});
