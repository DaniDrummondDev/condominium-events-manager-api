<?php

declare(strict_types=1);

use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\SupportRequestDTO;
use Application\Communication\UseCases\ReopenSupportRequest;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Entities\SupportRequest;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createResolvedRequestForReopen(): SupportRequest
{
    $request = SupportRequest::create(
        id: Uuid::generate(),
        userId: Uuid::generate(),
        subject: 'Problema',
        category: SupportRequestCategory::General,
        priority: SupportRequestPriority::Normal,
    );
    $request->pullDomainEvents();
    $request->startProgress();
    $request->resolve();
    $request->pullDomainEvents();

    return $request;
}

test('reopens a resolved request', function () {
    $request = createResolvedRequestForReopen();

    $repo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturn($request);
    $repo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ReopenSupportRequest($repo, $eventDispatcher);
    $result = $useCase->execute($request->id()->value());

    expect($result)->toBeInstanceOf(SupportRequestDTO::class)
        ->and($result->status)->toBe('open')
        ->and($result->closedAt)->toBeNull()
        ->and($result->closedReason)->toBeNull();
});

test('throws when request not found', function () {
    $repo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturnNull();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ReopenSupportRequest($repo, $eventDispatcher);
    $useCase->execute(Uuid::generate()->value());
})->throws(DomainException::class, 'Support request not found');

test('throws when trying to reopen from open', function () {
    $request = SupportRequest::create(
        id: Uuid::generate(),
        userId: Uuid::generate(),
        subject: 'Test',
        category: SupportRequestCategory::General,
        priority: SupportRequestPriority::Low,
    );
    $request->pullDomainEvents();

    $repo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $repo->shouldReceive('findById')->once()->andReturn($request);

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ReopenSupportRequest($repo, $eventDispatcher);
    $useCase->execute($request->id()->value());
})->throws(DomainException::class, "Cannot transition support request from 'open' to 'open'");
