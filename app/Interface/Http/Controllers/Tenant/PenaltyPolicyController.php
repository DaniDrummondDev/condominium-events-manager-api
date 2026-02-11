<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Interface\Http\Requests\Tenant\CreatePenaltyPolicyRequest;
use App\Interface\Http\Requests\Tenant\UpdatePenaltyPolicyRequest;
use App\Interface\Http\Resources\Tenant\PenaltyPolicyResource;
use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\DTOs\CreatePenaltyPolicyDTO;
use Application\Governance\DTOs\UpdatePenaltyPolicyDTO;
use Application\Governance\UseCases\ConfigurePenaltyPolicy;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PenaltyPolicyController
{
    public function index(PenaltyPolicyRepositoryInterface $repo): AnonymousResourceCollection
    {
        $policies = $repo->findActive();

        $dtos = array_map(
            fn ($policy) => ConfigurePenaltyPolicy::toDTO($policy),
            $policies,
        );

        return PenaltyPolicyResource::collection($dtos);
    }

    public function show(
        string $id,
        PenaltyPolicyRepositoryInterface $repo,
    ): JsonResponse {
        $policy = $repo->findById(Uuid::fromString($id));

        if ($policy === null) {
            return new JsonResponse([
                'error' => 'PENALTY_POLICY_NOT_FOUND',
                'message' => 'Penalty policy not found',
            ], 404);
        }

        $dto = ConfigurePenaltyPolicy::toDTO($policy);

        return (new PenaltyPolicyResource($dto))->response();
    }

    public function store(
        CreatePenaltyPolicyRequest $request,
        ConfigurePenaltyPolicy $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->create(new CreatePenaltyPolicyDTO(
                violationType: $request->validated('violation_type'),
                occurrenceThreshold: (int) $request->validated('occurrence_threshold'),
                penaltyType: $request->validated('penalty_type'),
                blockDays: $request->validated('block_days') !== null ? (int) $request->validated('block_days') : null,
            ));

            return (new PenaltyPolicyResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'PENALTY_POLICY_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function update(
        string $id,
        UpdatePenaltyPolicyRequest $request,
        ConfigurePenaltyPolicy $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->update(new UpdatePenaltyPolicyDTO(
                policyId: $id,
                occurrenceThreshold: $request->validated('occurrence_threshold') !== null ? (int) $request->validated('occurrence_threshold') : null,
                penaltyType: $request->validated('penalty_type'),
                blockDays: $request->validated('block_days') !== null ? (int) $request->validated('block_days') : null,
            ));

            return (new PenaltyPolicyResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'PENALTY_POLICY_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(
        string $id,
        ConfigurePenaltyPolicy $useCase,
    ): JsonResponse {
        try {
            $useCase->delete($id);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'PENALTY_POLICY_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
