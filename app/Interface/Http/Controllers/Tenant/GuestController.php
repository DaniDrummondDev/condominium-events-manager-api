<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\DenyGuestRequest;
use App\Interface\Http\Requests\Tenant\RegisterGuestRequest;
use App\Interface\Http\Resources\Tenant\GuestResource;
use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\DTOs\DenyGuestDTO;
use Application\People\DTOs\RegisterGuestDTO;
use Application\People\UseCases\CheckInGuest;
use Application\People\UseCases\CheckOutGuest;
use Application\People\UseCases\DenyGuestAccess;
use Application\People\UseCases\RegisterGuest;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestController
{
    public function index(
        string $reservationId,
        GuestRepositoryInterface $guestRepository,
    ): AnonymousResourceCollection {
        $guests = $guestRepository->findByReservation(Uuid::fromString($reservationId));
        $dtos = array_map(fn ($guest) => RegisterGuest::toDTO($guest), $guests);

        return GuestResource::collection($dtos);
    }

    public function store(
        string $reservationId,
        RegisterGuestRequest $request,
        RegisterGuest $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new RegisterGuestDTO(
                reservationId: $reservationId,
                name: $request->validated('name'),
                document: $request->validated('document'),
                phone: $request->validated('phone'),
                vehiclePlate: $request->validated('vehicle_plate'),
                relationship: $request->validated('relationship'),
                registeredBy: $user->userId->value(),
            ));

            return (new GuestResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND', 'SPACE_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function checkIn(
        string $id,
        CheckInGuest $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new GuestResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'GUEST_NOT_FOUND' => 404,
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
        CheckOutGuest $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new GuestResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'GUEST_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function deny(
        string $id,
        DenyGuestRequest $request,
        DenyGuestAccess $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new DenyGuestDTO(
                guestId: $id,
                deniedBy: $user->userId->value(),
                reason: $request->validated('reason'),
            ));

            return (new GuestResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'GUEST_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
