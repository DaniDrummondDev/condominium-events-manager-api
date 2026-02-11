<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Interface\Http\Requests\Tenant\CreateBlockRequest;
use App\Interface\Http\Requests\Tenant\UpdateBlockRequest;
use App\Interface\Http\Resources\Tenant\BlockResource;
use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\DTOs\BlockDTO;
use Application\Unit\DTOs\CreateBlockDTO;
use Application\Unit\DTOs\UpdateBlockDTO;
use Application\Unit\UseCases\CreateBlock;
use Application\Unit\UseCases\UpdateBlock;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BlockController
{
    public function index(BlockRepositoryInterface $blockRepository): AnonymousResourceCollection
    {
        $blocks = $blockRepository->findAllActive();

        $dtos = array_map(fn ($block) => new BlockDTO(
            id: $block->id()->value(),
            name: $block->name(),
            identifier: $block->identifier(),
            floors: $block->floors(),
            status: $block->status()->value,
            createdAt: $block->createdAt()->format('c'),
        ), $blocks);

        return BlockResource::collection($dtos);
    }

    public function store(CreateBlockRequest $request, CreateBlock $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new CreateBlockDTO(
                name: $request->validated('name'),
                identifier: $request->validated('identifier'),
                floors: $request->validated('floors'),
            ));

            return (new BlockResource($result))
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $id, BlockRepositoryInterface $blockRepository): JsonResponse
    {
        $block = $blockRepository->findById(Uuid::fromString($id));

        if ($block === null) {
            return new JsonResponse(['error' => 'BLOCK_NOT_FOUND', 'message' => 'Block not found'], 404);
        }

        $dto = new BlockDTO(
            id: $block->id()->value(),
            name: $block->name(),
            identifier: $block->identifier(),
            floors: $block->floors(),
            status: $block->status()->value,
            createdAt: $block->createdAt()->format('c'),
        );

        return (new BlockResource($dto))->response();
    }

    public function update(string $id, UpdateBlockRequest $request, UpdateBlock $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new UpdateBlockDTO(
                blockId: $id,
                name: $request->validated('name'),
                identifier: $request->validated('identifier'),
                floors: $request->validated('floors'),
            ));

            return (new BlockResource($result))->response();
        } catch (DomainException $e) {
            $status = $e->errorCode() === 'BLOCK_NOT_FOUND' ? 404 : 422;

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    public function destroy(string $id, BlockRepositoryInterface $blockRepository): JsonResponse
    {
        $block = $blockRepository->findById(Uuid::fromString($id));

        if ($block === null) {
            return new JsonResponse(['error' => 'BLOCK_NOT_FOUND', 'message' => 'Block not found'], 404);
        }

        $block->deactivate();
        $blockRepository->save($block);

        return new JsonResponse(null, 204);
    }
}
