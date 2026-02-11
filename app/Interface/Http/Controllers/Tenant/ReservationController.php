<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\CancelReservationRequest;
use App\Interface\Http\Requests\Tenant\CreateReservationRequest;
use App\Interface\Http\Requests\Tenant\RejectReservationRequest;
use App\Interface\Http\Resources\Tenant\ReservationDetailResource;
use App\Interface\Http\Resources\Tenant\ReservationResource;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Application\Reservation\DTOs\CancelReservationDTO;
use Application\Reservation\DTOs\CreateReservationDTO;
use Application\Reservation\DTOs\ListAvailableSlotsDTO;
use Application\Reservation\DTOs\RejectReservationDTO;
use Application\Reservation\DTOs\ReservationDTO;
use Application\Reservation\UseCases\ApproveReservation;
use Application\Reservation\UseCases\CancelReservation;
use Application\Reservation\UseCases\CheckInReservation;
use Application\Reservation\UseCases\CompleteReservation;
use Application\Reservation\UseCases\CreateReservation;
use Application\Reservation\UseCases\ListAvailableSlots;
use Application\Reservation\UseCases\MarkAsNoShow;
use Application\Reservation\UseCases\RejectReservation;
use Domain\Reservation\Entities\Reservation;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationController
{
    public function index(ReservationRepositoryInterface $reservationRepository): AnonymousResourceCollection
    {
        $reservations = $reservationRepository->findBySpace(Uuid::generate());

        // For listing, we return all reservations (filtered later by query params)
        // For now, return all — will be scoped by middleware/tenant context
        $dtos = [];

        return ReservationResource::collection($dtos);
    }

    public function store(
        CreateReservationRequest $request,
        CreateReservation $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CreateReservationDTO(
                spaceId: $request->validated('space_id'),
                unitId: $request->validated('unit_id'),
                residentId: $request->validated('resident_id'),
                title: $request->validated('title'),
                startDatetime: $request->validated('start_datetime'),
                endDatetime: $request->validated('end_datetime'),
                expectedGuests: (int) $request->validated('expected_guests'),
                notes: $request->validated('notes'),
            ));

            return (new ReservationDetailResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SPACE_NOT_FOUND', 'UNIT_NOT_FOUND',
                'RESIDENT_NOT_FOUND', 'RESERVATION_NOT_FOUND' => 404,
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
        ReservationRepositoryInterface $reservationRepository,
    ): JsonResponse {
        $reservation = $reservationRepository->findById(Uuid::fromString($id));

        if ($reservation === null) {
            return new JsonResponse([
                'error' => 'RESERVATION_NOT_FOUND',
                'message' => 'Reservation not found',
            ], 404);
        }

        $dto = $this->toDTO($reservation);

        return (new ReservationDetailResource($dto))->response();
    }

    public function approve(
        string $id,
        ApproveReservation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new ReservationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function reject(
        string $id,
        RejectReservationRequest $request,
        RejectReservation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new RejectReservationDTO(
                reservationId: $id,
                rejectedBy: $user->userId->value(),
                rejectionReason: $request->validated('rejection_reason'),
            ));

            return (new ReservationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function cancel(
        string $id,
        CancelReservationRequest $request,
        CancelReservation $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CancelReservationDTO(
                reservationId: $id,
                canceledBy: $user->userId->value(),
                cancellationReason: $request->validated('cancellation_reason'),
            ));

            return (new ReservationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND' => 404,
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
        CheckInReservation $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id);

            return (new ReservationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function complete(
        string $id,
        CompleteReservation $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id);

            return (new ReservationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function noShow(
        string $id,
        MarkAsNoShow $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id, $user->userId->value());

            return (new ReservationDetailResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'RESERVATION_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function availableSlots(
        string $id,
        Request $request,
        ListAvailableSlots $useCase,
    ): JsonResponse {
        try {
            $date = $request->query('date', now()->format('Y-m-d'));

            $slots = $useCase->execute(new ListAvailableSlotsDTO(
                spaceId: $id,
                date: (string) $date,
            ));

            return new JsonResponse(['data' => array_map(fn ($slot) => [
                'start_time' => $slot->startTime,
                'end_time' => $slot->endTime,
                'available' => $slot->available,
            ], $slots)]);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SPACE_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    private function toDTO(Reservation $reservation): ReservationDTO
    {
        return new ReservationDTO(
            id: $reservation->id()->value(),
            spaceId: $reservation->spaceId()->value(),
            unitId: $reservation->unitId()->value(),
            residentId: $reservation->residentId()->value(),
            status: $reservation->status()->value,
            title: $reservation->title(),
            startDatetime: $reservation->startDatetime()->format('c'),
            endDatetime: $reservation->endDatetime()->format('c'),
            expectedGuests: $reservation->expectedGuests(),
            notes: $reservation->notes(),
            approvedBy: $reservation->approvedBy()?->value(),
            approvedAt: $reservation->approvedAt()?->format('c'),
            rejectedBy: $reservation->rejectedBy()?->value(),
            rejectedAt: $reservation->rejectedAt()?->format('c'),
            rejectionReason: $reservation->rejectionReason(),
            canceledBy: $reservation->canceledBy()?->value(),
            canceledAt: $reservation->canceledAt()?->format('c'),
            cancellationReason: $reservation->cancellationReason(),
            completedAt: $reservation->completedAt()?->format('c'),
            noShowAt: $reservation->noShowAt()?->format('c'),
            noShowBy: $reservation->noShowBy()?->value(),
            checkedInAt: $reservation->checkedInAt()?->format('c'),
            createdAt: $reservation->createdAt()->format('c'),
        );
    }
}
