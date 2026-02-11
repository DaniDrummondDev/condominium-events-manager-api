<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Requests\Platform\CreateFeatureRequest;
use Application\Billing\Contracts\FeatureRepositoryInterface;
use Domain\Billing\Entities\Feature;
use Domain\Billing\Enums\FeatureType;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;

class FeatureController
{
    public function index(FeatureRepositoryInterface $featureRepository): JsonResponse
    {
        $features = $featureRepository->findAll();

        $data = array_map(fn (Feature $f) => [
            'id' => $f->id()->value(),
            'code' => $f->code(),
            'name' => $f->name(),
            'type' => $f->type()->value,
            'description' => $f->description(),
        ], $features);

        return new JsonResponse(['data' => $data]);
    }

    public function store(
        CreateFeatureRequest $request,
        FeatureRepositoryInterface $featureRepository,
    ): JsonResponse {
        $feature = new Feature(
            id: Uuid::generate(),
            code: $request->validated('code'),
            name: $request->validated('name'),
            type: FeatureType::from($request->validated('type')),
            description: $request->validated('description'),
        );

        $featureRepository->save($feature);

        return new JsonResponse([
            'data' => [
                'id' => $feature->id()->value(),
                'code' => $feature->code(),
                'name' => $feature->name(),
                'type' => $feature->type()->value,
                'description' => $feature->description(),
            ],
        ], 201);
    }

    public function show(string $id, FeatureRepositoryInterface $featureRepository): JsonResponse
    {
        $feature = $featureRepository->findById(Uuid::fromString($id));

        if ($feature === null) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_FOUND',
                'message' => 'Feature not found',
            ], 404);
        }

        return new JsonResponse([
            'data' => [
                'id' => $feature->id()->value(),
                'code' => $feature->code(),
                'name' => $feature->name(),
                'type' => $feature->type()->value,
                'description' => $feature->description(),
            ],
        ]);
    }
}
