<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\SupportRequestDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class ResolveSupportRequest
{
    public function __construct(
        private SupportRequestRepositoryInterface $requestRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(string $supportRequestId): SupportRequestDTO
    {
        $request = $this->requestRepository->findById(Uuid::fromString($supportRequestId));

        if ($request === null) {
            throw new DomainException(
                'Support request not found',
                'SUPPORT_REQUEST_NOT_FOUND',
                ['support_request_id' => $supportRequestId],
            );
        }

        $request->resolve();

        $this->requestRepository->save($request);
        $this->eventDispatcher->dispatchAll($request->pullDomainEvents());

        return CreateSupportRequest::toDTO($request);
    }
}
