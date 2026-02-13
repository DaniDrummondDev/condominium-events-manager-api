<?php

declare(strict_types=1);

use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\CreateSupportRequestDTO;
use Application\Communication\DTOs\SupportRequestDTO;
use Application\Communication\UseCases\CreateSupportRequest;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function makeCreateSupportRequestDTO(): CreateSupportRequestDTO
{
    return new CreateSupportRequestDTO(
        userId: Uuid::generate()->value(),
        subject: 'Torneira vazando',
        category: 'maintenance',
        priority: 'high',
    );
}

test('creates support request with open status', function () {
    $dto = makeCreateSupportRequestDTO();

    $repo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $repo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CreateSupportRequest($repo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SupportRequestDTO::class)
        ->and($result->subject)->toBe('Torneira vazando')
        ->and($result->category)->toBe('maintenance')
        ->and($result->priority)->toBe('high')
        ->and($result->status)->toBe('open')
        ->and($result->closedAt)->toBeNull()
        ->and($result->closedReason)->toBeNull();
});
