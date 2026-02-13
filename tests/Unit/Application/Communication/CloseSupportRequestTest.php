<?php

declare(strict_types=1);

use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\CloseSupportRequestDTO;
use Application\Communication\DTOs\SupportRequestDTO;
use Application\Communication\UseCases\CloseSupportRequest;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Entities\SupportRequest;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createOpenRequestForClose(): SupportRequest
{
    $request = SupportRequest::create(
        id: Uuid::generate(),
        userId: Uuid::generate(),
        subject: 'Barulho',
        category: SupportRequestCategory::Noise,
        priority: SupportRequestPriority::Normal,
    );
    $request->pullDomainEvents();

    return $request;
}

test('closes an open request with admin_closed reason', function () {
    $request = createOpenRequestForClose();

    $dto = new CloseSupportRequestDTO(
        supportRequestId: $request->id()->value(),
        reason: 'admin_closed',
    );

    $repo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturn($request);
    $repo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new CloseSupportRequest($repo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SupportRequestDTO::class)
        ->and($result->status)->toBe('closed')
        ->and($result->closedReason)->toBe('admin_closed')
        ->and($result->closedAt)->not->toBeNull();
});

test('throws when request not found', function () {
    $dto = new CloseSupportRequestDTO(
        supportRequestId: Uuid::generate()->value(),
        reason: 'resolved',
    );

    $repo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new CloseSupportRequest($repo, $eventDispatcher);
    $useCase->execute($dto);
})->throws(DomainException::class, 'Support request not found');
