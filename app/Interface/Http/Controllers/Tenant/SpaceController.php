<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\BlockSpaceRequest;
use App\Interface\Http\Requests\Tenant\ChangeSpaceStatusRequest;
use App\Interface\Http\Requests\Tenant\ConfigureSpaceRuleRequest;
use App\Interface\Http\Requests\Tenant\CreateSpaceRequest;
use App\Interface\Http\Requests\Tenant\SetSpaceAvailabilityRequest;
use App\Interface\Http\Requests\Tenant\UpdateSpaceRequest;
use App\Interface\Http\Resources\Tenant\SpaceAvailabilityResource;
use App\Interface\Http\Resources\Tenant\SpaceBlockResource;
use App\Interface\Http\Resources\Tenant\SpaceDetailResource;
use App\Interface\Http\Resources\Tenant\SpaceResource;
use App\Interface\Http\Resources\Tenant\SpaceRuleResource;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Application\Space\DTOs\BlockSpaceDTO;
use Application\Space\DTOs\ConfigureSpaceRuleDTO;
use Application\Space\DTOs\CreateSpaceDTO;
use Application\Space\DTOs\SetSpaceAvailabilityDTO;
use Application\Space\DTOs\SpaceAvailabilityDTO;
use Application\Space\DTOs\SpaceBlockDTO;
use Application\Space\DTOs\SpaceDetailDTO;
use Application\Space\DTOs\SpaceDTO;
use Application\Space\DTOs\SpaceRuleDTO;
use Application\Space\DTOs\UpdateSpaceDTO;
use Application\Space\UseCases\BlockSpace;
use Application\Space\UseCases\ChangeSpaceStatus;
use Application\Space\UseCases\ConfigureSpaceRules;
use Application\Space\UseCases\CreateSpace;
use Application\Space\UseCases\DeleteSpaceAvailability;
use Application\Space\UseCases\DeleteSpaceRule;
use Application\Space\UseCases\SetSpaceAvailability;
use Application\Space\UseCases\UnblockSpace;
use Application\Space\UseCases\UpdateSpace;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpaceController
{
    // ── Spaces CRUD ──────────────────────────────────────────────

    public function index(SpaceRepositoryInterface $spaceRepository): AnonymousResourceCollection
    {
        $spaces = $spaceRepository->findAllActive();

        $dtos = array_map(fn ($space) => new SpaceDTO(
            id: $space->id()->value(),
            name: $space->name(),
            description: $space->description(),
            type: $space->type()->value,
            status: $space->status()->value,
            capacity: $space->capacity(),
            requiresApproval: $space->requiresApproval(),
            maxDurationHours: $space->maxDurationHours(),
            maxAdvanceDays: $space->maxAdvanceDays(),
            minAdvanceHours: $space->minAdvanceHours(),
            cancellationDeadlineHours: $space->cancellationDeadlineHours(),
            createdAt: $space->createdAt()->format('c'),
        ), $spaces);

        return SpaceResource::collection($dtos);
    }

    public function store(CreateSpaceRequest $request, CreateSpace $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new CreateSpaceDTO(
                name: $request->validated('name'),
                description: $request->validated('description'),
                type: $request->validated('type'),
                capacity: (int) $request->validated('capacity'),
                requiresApproval: (bool) $request->validated('requires_approval', false),
                maxDurationHours: $request->validated('max_duration_hours') !== null
                    ? (int) $request->validated('max_duration_hours')
                    : null,
                maxAdvanceDays: (int) $request->validated('max_advance_days', 30),
                minAdvanceHours: (int) $request->validated('min_advance_hours', 24),
                cancellationDeadlineHours: (int) $request->validated('cancellation_deadline_hours', 24),
            ));

            return (new SpaceResource($result))
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
        SpaceRepositoryInterface $spaceRepository,
        SpaceAvailabilityRepositoryInterface $availabilityRepository,
        SpaceBlockRepositoryInterface $blockRepository,
        SpaceRuleRepositoryInterface $ruleRepository,
    ): JsonResponse {
        $space = $spaceRepository->findById(Uuid::fromString($id));

        if ($space === null) {
            return new JsonResponse(['error' => 'SPACE_NOT_FOUND', 'message' => 'Space not found'], 404);
        }

        $spaceId = $space->id();

        $spaceDto = new SpaceDTO(
            id: $spaceId->value(),
            name: $space->name(),
            description: $space->description(),
            type: $space->type()->value,
            status: $space->status()->value,
            capacity: $space->capacity(),
            requiresApproval: $space->requiresApproval(),
            maxDurationHours: $space->maxDurationHours(),
            maxAdvanceDays: $space->maxAdvanceDays(),
            minAdvanceHours: $space->minAdvanceHours(),
            cancellationDeadlineHours: $space->cancellationDeadlineHours(),
            createdAt: $space->createdAt()->format('c'),
        );

        $availabilities = array_map(fn ($a) => new SpaceAvailabilityDTO(
            id: $a->id()->value(),
            spaceId: $a->spaceId()->value(),
            dayOfWeek: $a->dayOfWeek(),
            startTime: $a->startTime(),
            endTime: $a->endTime(),
        ), $availabilityRepository->findBySpaceId($spaceId));

        $blocks = array_map(fn ($b) => new SpaceBlockDTO(
            id: $b->id()->value(),
            spaceId: $b->spaceId()->value(),
            reason: $b->reason(),
            startDatetime: $b->startDatetime()->format('c'),
            endDatetime: $b->endDatetime()->format('c'),
            blockedBy: $b->blockedBy()->value(),
            notes: $b->notes(),
            createdAt: $b->createdAt()->format('c'),
        ), $blockRepository->findBySpaceId($spaceId));

        $rules = array_map(fn ($r) => new SpaceRuleDTO(
            id: $r->id()->value(),
            spaceId: $r->spaceId()->value(),
            ruleKey: $r->ruleKey(),
            ruleValue: $r->ruleValue(),
            description: $r->description(),
        ), $ruleRepository->findBySpaceId($spaceId));

        $detail = new SpaceDetailDTO(
            space: $spaceDto,
            availabilities: $availabilities,
            blocks: $blocks,
            rules: $rules,
        );

        return (new SpaceDetailResource($detail))->response();
    }

    public function update(string $id, UpdateSpaceRequest $request, UpdateSpace $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new UpdateSpaceDTO(
                spaceId: $id,
                name: $request->validated('name'),
                description: $request->validated('description'),
                type: $request->validated('type'),
                capacity: $request->validated('capacity') !== null
                    ? (int) $request->validated('capacity')
                    : null,
                requiresApproval: $request->validated('requires_approval') !== null
                    ? (bool) $request->validated('requires_approval')
                    : null,
                maxDurationHours: $request->validated('max_duration_hours') !== null
                    ? (int) $request->validated('max_duration_hours')
                    : null,
                maxAdvanceDays: $request->validated('max_advance_days') !== null
                    ? (int) $request->validated('max_advance_days')
                    : null,
                minAdvanceHours: $request->validated('min_advance_hours') !== null
                    ? (int) $request->validated('min_advance_hours')
                    : null,
                cancellationDeadlineHours: $request->validated('cancellation_deadline_hours') !== null
                    ? (int) $request->validated('cancellation_deadline_hours')
                    : null,
            ));

            return (new SpaceResource($result))->response();
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'SPACE_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function changeStatus(string $id, ChangeSpaceStatusRequest $request, ChangeSpaceStatus $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute($id, $request->validated('status'));

            return (new SpaceResource($result))->response();
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'SPACE_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    // ── Availability ─────────────────────────────────────────────

    public function availabilityIndex(string $id, SpaceAvailabilityRepositoryInterface $availabilityRepository): JsonResponse
    {
        $spaceId = Uuid::fromString($id);

        $availabilities = array_map(fn ($a) => new SpaceAvailabilityDTO(
            id: $a->id()->value(),
            spaceId: $a->spaceId()->value(),
            dayOfWeek: $a->dayOfWeek(),
            startTime: $a->startTime(),
            endTime: $a->endTime(),
        ), $availabilityRepository->findBySpaceId($spaceId));

        return SpaceAvailabilityResource::collection($availabilities)->response();
    }

    public function availabilityStore(string $id, SetSpaceAvailabilityRequest $request, SetSpaceAvailability $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new SetSpaceAvailabilityDTO(
                spaceId: $id,
                dayOfWeek: (int) $request->validated('day_of_week'),
                startTime: $request->validated('start_time'),
                endTime: $request->validated('end_time'),
            ));

            return (new SpaceAvailabilityResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'SPACE_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function availabilityDestroy(string $id, string $availabilityId, DeleteSpaceAvailability $useCase): JsonResponse
    {
        try {
            $useCase->execute($availabilityId);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    // ── Blocks ───────────────────────────────────────────────────

    public function blockIndex(string $id, SpaceBlockRepositoryInterface $blockRepository): JsonResponse
    {
        $spaceId = Uuid::fromString($id);

        $blocks = array_map(fn ($b) => new SpaceBlockDTO(
            id: $b->id()->value(),
            spaceId: $b->spaceId()->value(),
            reason: $b->reason(),
            startDatetime: $b->startDatetime()->format('c'),
            endDatetime: $b->endDatetime()->format('c'),
            blockedBy: $b->blockedBy()->value(),
            notes: $b->notes(),
            createdAt: $b->createdAt()->format('c'),
        ), $blockRepository->findBySpaceId($spaceId));

        return SpaceBlockResource::collection($blocks)->response();
    }

    public function blockStore(string $id, BlockSpaceRequest $request, BlockSpace $useCase, AuthenticatedUser $user): JsonResponse
    {
        try {
            $result = $useCase->execute(new BlockSpaceDTO(
                spaceId: $id,
                reason: $request->validated('reason'),
                startDatetime: $request->validated('start_datetime'),
                endDatetime: $request->validated('end_datetime'),
                blockedBy: $user->userId->value(),
                notes: $request->validated('notes'),
            ));

            return (new SpaceBlockResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'SPACE_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function blockDestroy(string $id, string $blockId, UnblockSpace $useCase): JsonResponse
    {
        try {
            $useCase->execute($blockId);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    // ── Rules ────────────────────────────────────────────────────

    public function ruleIndex(string $id, SpaceRuleRepositoryInterface $ruleRepository): JsonResponse
    {
        $spaceId = Uuid::fromString($id);

        $rules = array_map(fn ($r) => new SpaceRuleDTO(
            id: $r->id()->value(),
            spaceId: $r->spaceId()->value(),
            ruleKey: $r->ruleKey(),
            ruleValue: $r->ruleValue(),
            description: $r->description(),
        ), $ruleRepository->findBySpaceId($spaceId));

        return SpaceRuleResource::collection($rules)->response();
    }

    public function ruleStore(string $id, ConfigureSpaceRuleRequest $request, ConfigureSpaceRules $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new ConfigureSpaceRuleDTO(
                spaceId: $id,
                ruleKey: $request->validated('rule_key'),
                ruleValue: $request->validated('rule_value'),
                description: $request->validated('description'),
            ));

            return (new SpaceRuleResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'SPACE_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function ruleUpdate(string $id, string $ruleId, ConfigureSpaceRuleRequest $request, ConfigureSpaceRules $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new ConfigureSpaceRuleDTO(
                spaceId: $id,
                ruleKey: $request->validated('rule_key'),
                ruleValue: $request->validated('rule_value'),
                description: $request->validated('description'),
            ));

            return (new SpaceRuleResource($result))->response();
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'SPACE_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function ruleDestroy(string $id, string $ruleId, DeleteSpaceRule $useCase): JsonResponse
    {
        try {
            $useCase->execute($ruleId);

            return new JsonResponse(null, 204);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
