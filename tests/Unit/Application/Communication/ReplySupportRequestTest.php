<?php

declare(strict_types=1);

use Application\Communication\Contracts\SupportMessageRepositoryInterface;
use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\AddSupportMessageDTO;
use Application\Communication\DTOs\SupportMessageDTO;
use Application\Communication\UseCases\ReplySupportRequest;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Entities\SupportRequest;
use Domain\Communication\Enums\ClosedReason;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

afterEach(fn () => Mockery::close());

function createOpenSupportRequestForReply(): SupportRequest
{
    $request = SupportRequest::create(
        id: Uuid::generate(),
        userId: Uuid::generate(),
        subject: 'Torneira',
        category: SupportRequestCategory::Maintenance,
        priority: SupportRequestPriority::Normal,
    );
    $request->pullDomainEvents();

    return $request;
}

function makeAddSupportMessageDTO(string $requestId): AddSupportMessageDTO
{
    return new AddSupportMessageDTO(
        supportRequestId: $requestId,
        senderId: Uuid::generate()->value(),
        body: 'Resposta à solicitação.',
        isInternal: false,
    );
}

test('replies and auto-transitions open request to in_progress', function () {
    $request = createOpenSupportRequestForReply();
    $dto = makeAddSupportMessageDTO($request->id()->value());

    $requestRepo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $requestRepo->shouldReceive('findById')->once()->andReturn($request);
    $requestRepo->shouldReceive('save')->once();

    $messageRepo = Mockery::mock(SupportMessageRepositoryInterface::class);
    $messageRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->twice();

    $useCase = new ReplySupportRequest($requestRepo, $messageRepo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SupportMessageDTO::class)
        ->and($result->body)->toBe('Resposta à solicitação.')
        ->and($result->isInternal)->toBeFalse();
});

test('replies to in_progress request without status change', function () {
    $request = createOpenSupportRequestForReply();
    $request->startProgress();
    $request->pullDomainEvents();

    $dto = makeAddSupportMessageDTO($request->id()->value());

    $requestRepo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $requestRepo->shouldReceive('findById')->once()->andReturn($request);

    $messageRepo = Mockery::mock(SupportMessageRepositoryInterface::class);
    $messageRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ReplySupportRequest($requestRepo, $messageRepo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result)->toBeInstanceOf(SupportMessageDTO::class);
});

test('throws when support request not found', function () {
    $dto = makeAddSupportMessageDTO(Uuid::generate()->value());

    $requestRepo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $requestRepo->shouldReceive('findById')->once()->andReturnNull();

    $messageRepo = Mockery::mock(SupportMessageRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ReplySupportRequest($requestRepo, $messageRepo, $eventDispatcher);
    $useCase->execute($dto);
})->throws(DomainException::class, 'Support request not found');

test('throws when support request is closed', function () {
    $request = createOpenSupportRequestForReply();
    $request->close(ClosedReason::AdminClosed);
    $request->pullDomainEvents();

    $dto = makeAddSupportMessageDTO($request->id()->value());

    $requestRepo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $requestRepo->shouldReceive('findById')->once()->andReturn($request);

    $messageRepo = Mockery::mock(SupportMessageRepositoryInterface::class);
    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

    $useCase = new ReplySupportRequest($requestRepo, $messageRepo, $eventDispatcher);
    $useCase->execute($dto);
})->throws(DomainException::class, 'Cannot reply to a closed support request');

test('creates internal message', function () {
    $request = createOpenSupportRequestForReply();
    $request->startProgress();
    $request->pullDomainEvents();

    $dto = new AddSupportMessageDTO(
        supportRequestId: $request->id()->value(),
        senderId: Uuid::generate()->value(),
        body: 'Nota interna.',
        isInternal: true,
    );

    $requestRepo = Mockery::mock(SupportRequestRepositoryInterface::class);
    $requestRepo->shouldReceive('findById')->once()->andReturn($request);

    $messageRepo = Mockery::mock(SupportMessageRepositoryInterface::class);
    $messageRepo->shouldReceive('save')->once();

    $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $eventDispatcher->shouldReceive('dispatchAll')->once();

    $useCase = new ReplySupportRequest($requestRepo, $messageRepo, $eventDispatcher);
    $result = $useCase->execute($dto);

    expect($result->isInternal)->toBeTrue();
});
