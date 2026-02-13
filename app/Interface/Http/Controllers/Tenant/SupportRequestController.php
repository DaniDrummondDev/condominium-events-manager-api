<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\AddSupportMessageRequest;
use App\Interface\Http\Requests\Tenant\CloseSupportRequestRequest;
use App\Interface\Http\Requests\Tenant\CreateSupportRequestRequest;
use App\Interface\Http\Resources\Tenant\SupportMessageResource;
use App\Interface\Http\Resources\Tenant\SupportRequestResource;
use Application\Communication\Contracts\SupportMessageRepositoryInterface;
use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Application\Communication\DTOs\AddSupportMessageDTO;
use Application\Communication\DTOs\CloseSupportRequestDTO;
use Application\Communication\DTOs\CreateSupportRequestDTO;
use Application\Communication\UseCases\CloseSupportRequest;
use Application\Communication\UseCases\CreateSupportRequest;
use Application\Communication\UseCases\ReopenSupportRequest;
use Application\Communication\UseCases\ReplySupportRequest;
use Application\Communication\UseCases\ResolveSupportRequest;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportRequestController
{
    public function index(
        SupportRequestRepositoryInterface $requestRepository,
    ): AnonymousResourceCollection {
        $requests = $requestRepository->findAll();
        $dtos = array_map(fn ($r) => CreateSupportRequest::toDTO($r), $requests);

        return SupportRequestResource::collection($dtos);
    }

    public function store(
        CreateSupportRequestRequest $request,
        CreateSupportRequest $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CreateSupportRequestDTO(
                userId: $user->userId->value(),
                subject: $request->validated('subject'),
                category: $request->validated('category'),
                priority: $request->validated('priority'),
            ));

            return (new SupportRequestResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(
        string $id,
        SupportRequestRepositoryInterface $requestRepository,
        SupportMessageRepositoryInterface $messageRepository,
    ): JsonResponse {
        $request = $requestRepository->findById(Uuid::fromString($id));

        if ($request === null) {
            return new JsonResponse([
                'error' => 'SUPPORT_REQUEST_NOT_FOUND',
                'message' => 'Support request not found',
            ], 404);
        }

        $messages = $messageRepository->findByRequest(Uuid::fromString($id));
        $messageDtos = array_map(fn ($m) => ReplySupportRequest::toDTO($m), $messages);

        return new JsonResponse([
            'data' => (new SupportRequestResource(CreateSupportRequest::toDTO($request)))->toArray(request()),
            'messages' => SupportMessageResource::collection($messageDtos)->toArray(request()),
        ]);
    }

    public function addMessage(
        string $id,
        AddSupportMessageRequest $request,
        ReplySupportRequest $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new AddSupportMessageDTO(
                supportRequestId: $id,
                senderId: $user->userId->value(),
                body: $request->validated('body'),
                isInternal: (bool) $request->validated('is_internal', false),
            ));

            return (new SupportMessageResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SUPPORT_REQUEST_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function resolve(
        string $id,
        ResolveSupportRequest $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id);

            return (new SupportRequestResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SUPPORT_REQUEST_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function close(
        string $id,
        CloseSupportRequestRequest $request,
        CloseSupportRequest $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CloseSupportRequestDTO(
                supportRequestId: $id,
                reason: $request->validated('reason'),
            ));

            return (new SupportRequestResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SUPPORT_REQUEST_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function reopen(
        string $id,
        ReopenSupportRequest $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id);

            return (new SupportRequestResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SUPPORT_REQUEST_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
