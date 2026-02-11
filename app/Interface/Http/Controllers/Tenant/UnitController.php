<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Interface\Http\Requests\Tenant\CreateUnitRequest;
use App\Interface\Http\Requests\Tenant\UpdateUnitRequest;
use App\Interface\Http\Resources\Tenant\UnitResource;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Application\Unit\DTOs\CreateUnitDTO;
use Application\Unit\DTOs\UnitDTO;
use Application\Unit\DTOs\UpdateUnitDTO;
use Application\Unit\UseCases\CreateUnit;
use Application\Unit\UseCases\DeactivateUnit;
use Application\Unit\UseCases\UpdateUnit;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController
{
    public function index(UnitRepositoryInterface $unitRepository): AnonymousResourceCollection
    {
        $count = $unitRepository->countByTenant();
        $dtos = [];

        if ($count > 0) {
            // TODO: Replace with paginated query when available
        }

        return UnitResource::collection($dtos);
    }

    public function store(CreateUnitRequest $request, CreateUnit $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new CreateUnitDTO(
                blockId: $request->validated('block_id'),
                number: $request->validated('number'),
                floor: $request->validated('floor'),
                type: $request->validated('type'),
            ));

            return (new UnitResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $id, UnitRepositoryInterface $unitRepository): JsonResponse
    {
        $unit = $unitRepository->findById(Uuid::fromString($id));

        if ($unit === null) {
            return new JsonResponse(['error' => 'UNIT_NOT_FOUND', 'message' => 'Unit not found'], 404);
        }

        $dto = new UnitDTO(
            id: $unit->id()->value(),
            blockId: $unit->blockId()?->value(),
            number: $unit->number(),
            floor: $unit->floor(),
            type: $unit->type()->value,
            status: $unit->status()->value,
            isOccupied: $unit->isOccupied(),
            createdAt: $unit->createdAt()->format('c'),
        );

        return (new UnitResource($dto))->response();
    }

    public function update(string $id, UpdateUnitRequest $request, UpdateUnit $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new UpdateUnitDTO(
                unitId: $id,
                number: $request->validated('number'),
                floor: $request->validated('floor'),
                type: $request->validated('type'),
            ));

            return (new UnitResource($result))->response();
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'UNIT_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function deactivate(string $id, DeactivateUnit $useCase): JsonResponse
    {
        try {
            $useCase->execute($id);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'UNIT_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
