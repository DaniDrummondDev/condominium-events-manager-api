<?php

declare(strict_types=1);

namespace Application\Communication\UseCases;

use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\CloseSupportRequestDTO;
use Application\Communication\DTOs\SupportRequestDTO;
use Application\Shared\Contracts\EventDispatcherInterface;
use Domain\Communication\Enums\ClosedReason;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CloseSupportRequest
{
    public function __construct(
        private SupportRequestRepositoryInterface $requestRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CloseSupportRequestDTO $dto): SupportRequestDTO
    {
        $request = $this->requestRepository->findById(Uuid::fromString($dto->supportRequestId));

        if ($request === null) {
            throw new DomainException(
                'Support request not found',
                'SUPPORT_REQUEST_NOT_FOUND',
                ['support_request_id' => $dto->supportRequestId],
            );
        }

        $request->close(ClosedReason::from($dto->reason));

        $this->requestRepository->save($request);
        $this->eventDispatcher->dispatchAll($request->pullDomainEvents());

        return CreateSupportRequest::toDTO($request);
    }
}
