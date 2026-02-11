<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\DTOs\CreatePenaltyPolicyDTO;
use Application\Governance\DTOs\PenaltyPolicyDTO;
use Application\Governance\DTOs\UpdatePenaltyPolicyDTO;
use Application\Governance\UseCases\ConfigurePenaltyPolicy;
use Domain\Governance\Entities\PenaltyPolicy;
use Domain\Governance\Enums\PenaltyType;
use Domain\Governance\Enums\ViolationType;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('create() creates policy and returns PenaltyPolicyDTO', function () {
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('save')->once();

    $useCase = new ConfigurePenaltyPolicy($policyRepo);

    $dto = new CreatePenaltyPolicyDTO(
        violationType: 'no_show',
        occurrenceThreshold: 3,
        penaltyType: 'temporary_block',
        blockDays: 15,
    );

    $result = $useCase->create($dto);

    expect($result)->toBeInstanceOf(PenaltyPolicyDTO::class)
        ->and($result->violationType)->toBe('no_show')
        ->and($result->occurrenceThreshold)->toBe(3)
        ->and($result->penaltyType)->toBe('temporary_block')
        ->and($result->blockDays)->toBe(15)
        ->and($result->isActive)->toBeTrue();
});

test('update() updates policy', function () {
    $policy = PenaltyPolicy::create(
        id: Uuid::generate(),
        violationType: ViolationType::NoShow,
        occurrenceThreshold: 2,
        penaltyType: PenaltyType::TemporaryBlock,
        blockDays: 15,
    );

    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findById')->once()->andReturn($policy);
    $policyRepo->shouldReceive('save')->once();

    $useCase = new ConfigurePenaltyPolicy($policyRepo);

    $dto = new UpdatePenaltyPolicyDTO(
        policyId: $policy->id()->value(),
        occurrenceThreshold: 5,
        penaltyType: 'permanent_block',
        blockDays: 30,
    );

    $result = $useCase->update($dto);

    expect($result)->toBeInstanceOf(PenaltyPolicyDTO::class)
        ->and($result->occurrenceThreshold)->toBe(5)
        ->and($result->penaltyType)->toBe('permanent_block')
        ->and($result->blockDays)->toBe(30);
});

test('update() throws PENALTY_POLICY_NOT_FOUND when policy does not exist', function () {
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findById')->once()->andReturnNull();

    $useCase = new ConfigurePenaltyPolicy($policyRepo);

    $dto = new UpdatePenaltyPolicyDTO(
        policyId: Uuid::generate()->value(),
        occurrenceThreshold: 5,
        penaltyType: null,
        blockDays: null,
    );

    $useCase->update($dto);
})->throws(DomainException::class, 'Penalty policy not found');

test('delete() deletes policy', function () {
    $policy = PenaltyPolicy::create(
        id: Uuid::generate(),
        violationType: ViolationType::NoShow,
        occurrenceThreshold: 2,
        penaltyType: PenaltyType::TemporaryBlock,
        blockDays: 15,
    );

    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findById')->once()->andReturn($policy);
    $policyRepo->shouldReceive('delete')
        ->once()
        ->with(Mockery::on(fn ($id) => $id->value() === $policy->id()->value()));

    $useCase = new ConfigurePenaltyPolicy($policyRepo);
    $useCase->delete($policy->id()->value());
});

test('delete() throws PENALTY_POLICY_NOT_FOUND when policy does not exist', function () {
    $policyRepo = Mockery::mock(PenaltyPolicyRepositoryInterface::class);
    $policyRepo->shouldReceive('findById')->once()->andReturnNull();

    $useCase = new ConfigurePenaltyPolicy($policyRepo);

    $useCase->delete(Uuid::generate()->value());
})->throws(DomainException::class, 'Penalty policy not found');
