<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\CreateRuleRequest;
use App\Interface\Http\Requests\Tenant\UpdateRuleRequest;
use App\Interface\Http\Resources\Tenant\CondominiumRuleResource;
use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\DTOs\CreateRuleDTO;
use Application\Governance\DTOs\UpdateRuleDTO;
use Application\Governance\UseCases\CreateRule;
use Application\Governance\UseCases\DeleteRule;
use Application\Governance\UseCases\UpdateRule;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CondominiumRuleController
{
    public function index(CondominiumRuleRepositoryInterface $repo): AnonymousResourceCollection
    {
        $rules = $repo->findActive();

        $dtos = array_map(
            fn ($rule) => CreateRule::toDTO($rule),
            $rules,
        );

        return CondominiumRuleResource::collection($dtos);
    }

    public function show(
        string $id,
        CondominiumRuleRepositoryInterface $repo,
    ): JsonResponse {
        $rule = $repo->findById(Uuid::fromString($id));

        if ($rule === null) {
            return new JsonResponse([
                'error' => 'RULE_NOT_FOUND',
                'message' => 'Condominium rule not found',
            ], 404);
        }

        $dto = CreateRule::toDTO($rule);

        return (new CondominiumRuleResource($dto))->response();
    }

    public function store(
        CreateRuleRequest $request,
        CreateRule $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CreateRuleDTO(
                title: $request->validated('title'),
                description: $request->validated('description'),
                category: $request->validated('category'),
                order: (int) ($request->validated('order') ?? 0),
                createdBy: $user->userId->value(),
            ));

            return (new CondominiumRuleResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RULE_NOT_FOUND' => 404,
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
        UpdateRuleRequest $request,
        UpdateRule $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new UpdateRuleDTO(
                ruleId: $id,
                title: $request->validated('title'),
                description: $request->validated('description'),
                category: $request->validated('category'),
                order: $request->validated('order') !== null ? (int) $request->validated('order') : null,
            ));

            return (new CondominiumRuleResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RULE_NOT_FOUND' => 404,
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
        DeleteRule $useCase,
    ): JsonResponse {
        try {
            $useCase->execute($id);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RULE_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
