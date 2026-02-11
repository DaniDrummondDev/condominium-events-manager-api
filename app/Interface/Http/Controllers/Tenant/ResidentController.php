<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Interface\Http\Requests\Tenant\ActivateResidentRequest;
use App\Interface\Http\Requests\Tenant\InviteResidentRequest;
use App\Interface\Http\Resources\Tenant\ResidentResource;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\DTOs\InviteResidentDTO;
use Application\Unit\DTOs\ResidentDTO;
use Application\Unit\UseCases\ActivateResident;
use Application\Unit\UseCases\DeactivateResident;
use Application\Unit\UseCases\InviteResident;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResidentController
{
    public function index(string $unitId, ResidentRepositoryInterface $residentRepository): AnonymousResourceCollection
    {
        $residents = $residentRepository->findByUnitId(Uuid::fromString($unitId));

        $dtos = array_map(fn ($resident) => new ResidentDTO(
            id: $resident->id()->value(),
            unitId: $resident->unitId()->value(),
            tenantUserId: $resident->tenantUserId()->value(),
            name: $resident->name(),
            email: $resident->email(),
            phone: $resident->phone(),
            roleInUnit: $resident->roleInUnit()->value,
            isPrimary: $resident->isPrimary(),
            status: $resident->status()->value,
            movedInAt: $resident->movedInAt()->format('c'),
            movedOutAt: $resident->movedOutAt()?->format('c'),
        ), $residents);

        return ResidentResource::collection($dtos);
    }

    public function show(string $id, ResidentRepositoryInterface $residentRepository): JsonResponse
    {
        $resident = $residentRepository->findById(Uuid::fromString($id));

        if ($resident === null) {
            return new JsonResponse(['error' => 'RESIDENT_NOT_FOUND', 'message' => 'Resident not found'], 404);
        }

        $dto = new ResidentDTO(
            id: $resident->id()->value(),
            unitId: $resident->unitId()->value(),
            tenantUserId: $resident->tenantUserId()->value(),
            name: $resident->name(),
            email: $resident->email(),
            phone: $resident->phone(),
            roleInUnit: $resident->roleInUnit()->value,
            isPrimary: $resident->isPrimary(),
            status: $resident->status()->value,
            movedInAt: $resident->movedInAt()->format('c'),
            movedOutAt: $resident->movedOutAt()?->format('c'),
        );

        return (new ResidentResource($dto))->response();
    }

    public function invite(InviteResidentRequest $request, InviteResident $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new InviteResidentDTO(
                unitId: $request->validated('unit_id'),
                name: $request->validated('name'),
                email: $request->validated('email'),
                phone: $request->validated('phone'),
                document: $request->validated('document'),
                roleInUnit: $request->validated('role_in_unit'),
            ));

            return (new ResidentResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function activate(ActivateResidentRequest $request, ActivateResident $useCase): JsonResponse
    {
        try {
            $useCase->execute(
                $request->validated('token'),
                $request->validated('password'),
            );

            return new JsonResponse(['message' => 'Resident activated successfully']);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function deactivate(string $id, DeactivateResident $useCase): JsonResponse
    {
        try {
            $useCase->execute($id);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'RESIDENT_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
