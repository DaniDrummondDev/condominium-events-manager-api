<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\RegisterViolationRequest;
use App\Interface\Http\Requests\Tenant\RevokePenaltyRequest;
use App\Interface\Http\Requests\Tenant\RevokeViolationRequest;
use App\Interface\Http\Resources\Tenant\PenaltyResource;
use App\Interface\Http\Resources\Tenant\ViolationDetailResource;
use App\Interface\Http\Resources\Tenant\ViolationResource;
use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Application\Governance\DTOs\RegisterViolationDTO;
use Application\Governance\DTOs\RevokePenaltyDTO;
use Application\Governance\UseCases\ApplyPenalty;
use Application\Governance\UseCases\RegisterViolation;
use Application\Governance\UseCases\RevokePenalty;
use Application\Governance\UseCases\RevokeViolation;
use Application\Governance\UseCases\UpholdViolation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GovernanceController
{
    public function listViolations(ViolationRepositoryInterface $repo): AnonymousResourceCollection
    {
        return ViolationResource::collection([]);
    }

    public function showViolation(
        string $id,
        ViolationRepositoryInterface $repo,
    ): JsonResponse {
        $violation = $repo->findById(Uuid::fromString($id));

        if ($violation === null) {
            return new JsonResponse([
                'error' => 'VIOLATION_NOT_FOUND',
                'message' => 'Violation not found',
            ], 404);
        }

        $dto = RegisterViolation::toDTO($violation);

        return (new ViolationDetailResource($dto))->response();
    }

    public function registerViolation(
        RegisterViolationRequest $request,
        RegisterViolation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new RegisterViolationDTO(
                unitId: $request->validated('unit_id'),
                tenantUserId: $request->validated('tenant_user_id'),
                reservationId: $request->validated('reservation_id'),
                ruleId: $request->validated('rule_id'),
                type: $request->validated('type'),
                severity: $request->validated('severity'),
                description: $request->validated('description'),
                createdBy: $user->userId->value(),
            ));

            return (new ViolationDetailResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'VIOLATION_NOT_FOUND', 'RULE_NOT_FOUND',
                'UNIT_NOT_FOUND', 'RESIDENT_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function upholdViolation(
        string $id,
        UpholdViolation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new ViolationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'VIOLATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function revokeViolation(
        string $id,
        RevokeViolationRequest $request,
        RevokeViolation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value(), $request->validated('reason'));

            return (new ViolationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'VIOLATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function listPenalties(PenaltyRepositoryInterface $repo): AnonymousResourceCollection
    {
        return PenaltyResource::collection([]);
    }

    public function showPenalty(
        string $id,
        PenaltyRepositoryInterface $repo,
    ): JsonResponse {
        $penalty = $repo->findById(Uuid::fromString($id));

        if ($penalty === null) {
            return new JsonResponse([
                'error' => 'PENALTY_NOT_FOUND',
                'message' => 'Penalty not found',
            ], 404);
        }

        $dto = ApplyPenalty::toDTO($penalty);

        return (new PenaltyResource($dto))->response();
    }

    public function revokePenalty(
        string $id,
        RevokePenaltyRequest $request,
        RevokePenalty $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new RevokePenaltyDTO(
                penaltyId: $id,
                revokedBy: $user->userId->value(),
                reason: $request->validated('reason'),
            ));

            return (new PenaltyResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'PENALTY_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
