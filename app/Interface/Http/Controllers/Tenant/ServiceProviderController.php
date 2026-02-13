<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Tenant;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Interface\Http\Requests\Tenant\RegisterServiceProviderRequest;
use App\Interface\Http\Requests\Tenant\UpdateServiceProviderRequest;
use App\Interface\Http\Resources\Tenant\ServiceProviderResource;
use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\DTOs\RegisterServiceProviderDTO;
use Application\People\DTOs\UpdateServiceProviderDTO;
use Application\People\UseCases\RegisterServiceProvider;
use Application\People\UseCases\UpdateServiceProvider;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceProviderController
{
    public function index(
        ServiceProviderRepositoryInterface $repository,
    ): AnonymousResourceCollection {
        $providers = $repository->findAll();
        $dtos = array_map(fn ($p) => RegisterServiceProvider::toDTO($p), $providers);

        return ServiceProviderResource::collection($dtos);
    }

    public function store(
        RegisterServiceProviderRequest $request,
        RegisterServiceProvider $useCase,
        AuthenticatedUser $user,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new RegisterServiceProviderDTO(
                companyName: $request->validated('company_name'),
                name: $request->validated('name'),
                document: $request->validated('document'),
                phone: $request->validated('phone'),
                serviceType: $request->validated('service_type'),
                notes: $request->validated('notes'),
                createdBy: $user->userId->value(),
            ));

            return (new ServiceProviderResource($result))
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
        ServiceProviderRepositoryInterface $repository,
    ): JsonResponse {
        $provider = $repository->findById(Uuid::fromString($id));

        if ($provider === null) {
            return new JsonResponse([
                'error' => 'SERVICE_PROVIDER_NOT_FOUND',
                'message' => 'Service provider not found',
            ], 404);
        }

        $dto = RegisterServiceProvider::toDTO($provider);

        return (new ServiceProviderResource($dto))->response();
    }

    public function update(
        string $id,
        UpdateServiceProviderRequest $request,
        UpdateServiceProvider $useCase,
    ): JsonResponse {
        try {
            $result = $useCase->execute(new UpdateServiceProviderDTO(
                serviceProviderId: $id,
                companyName: $request->validated('company_name'),
                name: $request->validated('name'),
                phone: $request->validated('phone'),
                serviceType: $request->validated('service_type'),
                notes: $request->validated('notes'),
            ));

            return (new ServiceProviderResource($result))->response();
        } catch (DomainException $e) {
            $status = match ($e->errorCode()) {
                'SERVICE_PROVIDER_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
