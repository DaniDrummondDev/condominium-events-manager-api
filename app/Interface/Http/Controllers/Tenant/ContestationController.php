<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\ReviewContestationRequest;
use App\Interface\Http\Requests\Tenant\SubmitContestationRequest;
use App\Interface\Http\Resources\Tenant\ContestationResource;
use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use Application\Governance\DTOs\ContestViolationDTO;
use Application\Governance\DTOs\ReviewContestationDTO;
use Application\Governance\UseCases\ContestViolation;
use Application\Governance\UseCases\ReviewContestation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContestationController
{
    public function submitContestation(
        string $violationId,
        SubmitContestationRequest $request,
        ContestViolation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new ContestViolationDTO(
                violationId: $violationId,
                tenantUserId: $user->userId->value(),
                reason: $request->validated('reason'),
            ));

            return (new ContestationResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'VIOLATION_NOT_FOUND', 'CONTESTATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function showContestation(
        string $id,
        ViolationContestationRepositoryInterface $repo,
    ): JsonResponse {
        $contestation = $repo->findById(Uuid::fromString($id));

        if ($contestation === null) {
            return new JsonResponse([
                'error' => 'CONTESTATION_NOT_FOUND',
                'message' => 'Contestation not found',
            ], 404);
        }

        $dto = ContestViolation::toDTO($contestation);

        return (new ContestationResource($dto))->response();
    }

    public function listContestations(
        ViolationContestationRepositoryInterface $repo,
    ): AnonymousResourceCollection {
        $contestations = $repo->findAll();

        $dtos = array_map(
            fn ($contestation) => ContestViolation::toDTO($contestation),
            $contestations,
        );

        return ContestationResource::collection($dtos);
    }

    public function reviewContestation(
        string $id,
        ReviewContestationRequest $request,
        ReviewContestation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new ReviewContestationDTO(
                contestationId: $id,
                respondedBy: $user->userId->value(),
                accepted: (bool) $request->validated('accepted'),
                response: $request->validated('response'),
            ));

            return (new ContestationResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'CONTESTATION_NOT_FOUND', 'VIOLATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
