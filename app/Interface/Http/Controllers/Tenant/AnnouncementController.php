<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\CreateAnnouncementRequest;
use App\Interface\Http\Resources\Tenant\AnnouncementResource;
use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\DTOs\CreateAnnouncementDTO;
use Application\Communication\UseCases\ArchiveAnnouncement;
use Application\Communication\UseCases\CreateAnnouncement;
use Application\Communication\UseCases\MarkAnnouncementAsRead;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController
{
    public function index(
        AnnouncementRepositoryInterface $announcementRepository,
    ): AnonymousResourceCollection {
        $announcements = $announcementRepository->findAll();
        $dtos = array_map(fn ($a) => CreateAnnouncement::toDTO($a), $announcements);

        return AnnouncementResource::collection($dtos);
    }

    public function store(
        CreateAnnouncementRequest $request,
        CreateAnnouncement $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new CreateAnnouncementDTO(
                title: $request->validated('title'),
                body: $request->validated('body'),
                priority: $request->validated('priority'),
                audienceType: $request->validated('audience_type'),
                audienceIds: $request->validated('audience_ids'),
                publishedBy: $user->userId->value(),
                expiresAt: $request->validated('expires_at'),
            ));

            return (new AnnouncementResource($result))
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
        AnnouncementRepositoryInterface $announcementRepository,
    ): JsonResponse {
        $announcement = $announcementRepository->findById(
            \Domain\Shared\ValueObjects\Uuid::fromString($id),
        );

        if ($announcement === null) {
            return new JsonResponse([
                'error' => 'ANNOUNCEMENT_NOT_FOUND',
                'message' => 'Announcement not found',
            ], 404);
        }

        return (new AnnouncementResource(CreateAnnouncement::toDTO($announcement)))->response();
    }

    public function archive(
        string $id,
        ArchiveAnnouncement $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute($id);

            return (new AnnouncementResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'ANNOUNCEMENT_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function markAsRead(
        string $id,
        MarkAnnouncementAsRead $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $useCase->execute($id, $user->userId->value());

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'ANNOUNCEMENT_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
