<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Tenant\Repositories;

use App\Infrastructure\Persistence\Tenant\Models\ServiceProviderModel;
use Application\People\Contracts\ServiceProviderRepositoryInterface;
use DateTimeImmutable;
use Domain\People\Entities\ServiceProvider;
use Domain\People\Enums\ServiceProviderStatus;
use Domain\People\Enums\ServiceType;
use Domain\Shared\ValueObjects\Uuid;

class EloquentServiceProviderRepository implements ServiceProviderRepositoryInterface
{
    public function findById(Uuid $id): ?ServiceProvider
    {
        $model = ServiceProviderModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<ServiceProvider>
     */
    public function findAll(): array
    {
        return ServiceProviderModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceProviderModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findByDocument(string $document): ?ServiceProvider
    {
        $model = ServiceProviderModel::query()
            ->where('document', $document)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(ServiceProvider $provider): void
    {
        ServiceProviderModel::query()->updateOrCreate(
            ['id' => $provider->id()->value()],
            [
                'company_name' => $provider->companyName(),
                'name' => $provider->name(),
                'document' => $provider->document(),
                'phone' => $provider->phone(),
                'service_type' => $provider->serviceType()->value,
                'status' => $provider->status()->value,
                'notes' => $provider->notes(),
                'created_by' => $provider->createdBy()->value(),
            ],
        );
    }

    private function toDomain(ServiceProviderModel $model): ServiceProvider
    {
        /** @var string $createdAtRaw */
        $createdAtRaw = $model->getRawOriginal('created_at');

        return new ServiceProvider(
            id: Uuid::fromString($model->id),
            companyName: $model->company_name,
            name: $model->name,
            document: $model->document,
            phone: $model->phone,
            serviceType: ServiceType::from($model->service_type),
            status: ServiceProviderStatus::from($model->status),
            notes: $model->notes,
            createdBy: Uuid::fromString($model->created_by),
            createdAt: new DateTimeImmutable($createdAtRaw),
        );
    }
}
