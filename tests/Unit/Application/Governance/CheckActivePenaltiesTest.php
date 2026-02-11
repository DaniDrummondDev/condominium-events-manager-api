<?php

declare(strict_types=1);

use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\UseCases\CheckActivePenalties;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

test('returns true when hasActiveBlock returns true', function () {
    $unitId = Uuid::generate()->value();

    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('hasActiveBlock')
        ->once()
        ->andReturn(true);

    $useCase = new CheckActivePenalties($penaltyRepo);
    $result = $useCase->hasActiveBlock($unitId);

    expect($result)->toBeTrue();
});

test('returns false when hasActiveBlock returns false', function () {
    $unitId = Uuid::generate()->value();

    $penaltyRepo = Mockery::mock(PenaltyRepositoryInterface::class);
    $penaltyRepo->shouldReceive('hasActiveBlock')
        ->once()
        ->andReturn(false);

    $useCase = new CheckActivePenalties($penaltyRepo);
    $result = $useCase->hasActiveBlock($unitId);

    expect($result)->toBeFalse();
});
