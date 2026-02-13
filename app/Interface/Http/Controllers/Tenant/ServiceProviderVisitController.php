<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\ScheduleVisitRequest;
use App\Interface\Http\Resources\Tenant\ServiceProviderVisitResource;
use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use Application\People\DTOs\ScheduleVisitDTO;
use Application\People\UseCases\CheckInServiceProvider;
use Application\People\UseCases\CheckOutServiceProvider;
use Application\People\UseCases\ScheduleServiceProviderVisit;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceProviderVisitController
{
    public function index(
        ServiceProviderVisitRepositoryInterface $repository,
    ): AnonymousResourceCollection {
        // Will be filtered by query params in future — for now returns all
        $visits = $repository->findByUnit(Uuid::generate());
        $dtos = array_map(fn ($v) => ScheduleServiceProviderVisit::toDTO($v), $visits);

        return ServiceProviderVisitResource::collection($dtos);
    }

    public function store(
        ScheduleVisitRequest $request,
        ScheduleServiceProviderVisit $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new ScheduleVisitDTO(
                serviceProviderId: $request->validated('service_provider_id'),
                unitId: $request->validated('unit_id'),
                reservationId: $request->validated('reservation_id'),
                scheduledDate: $request->validated('scheduled_date'),
                purpose: $request->validated('purpose'),
                notes: $request->validated('notes'),
                createdBy: $user->userId->value(),
            ));

            return (new ServiceProviderVisitResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SERVICE_PROVIDER_NOT_FOUND', 'UNIT_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function show(
        string $id,
        ServiceProviderVisitRepositoryInterface $repository,
    ): JsonResponse {
        $visit = $repository->findById(Uuid::fromString($id));

        if ($visit === null) {
            return new JsonResponse([
                'error' => 'VISIT_NOT_FOUND',
                'message' => 'Service provider visit not found',
            ], 404);
        }

        $dto = ScheduleServiceProviderVisit::toDTO($visit);

        return (new ServiceProviderVisitResource($dto))->response();
    }

    public function checkIn(
        string $id,
        CheckInServiceProvider $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new ServiceProviderVisitResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'VISIT_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function checkOut(
        string $id,
        CheckOutServiceProvider $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new ServiceProviderVisitResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'VISIT_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
