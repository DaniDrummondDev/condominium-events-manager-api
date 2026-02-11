<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PlanVersionModel;
use Application\Billing\Contracts\PlanVersionRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\PlanVersion;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\PlanStatus;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPlanVersionRepository implements PlanVersionRepositoryInterface
{
    public function findById(Uuid $id): ?PlanVersion
    {
        $model = PlanVersionModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findActiveByPlanId(Uuid $planId): ?PlanVersion
    {
        $model = PlanVersionModel::query()
            ->where('plan_id', $planId->value())
            ->where('status', PlanStatus::Active->value)
            ->orderByDesc('version')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByPlanIdAndVersion(Uuid $planId, int $version): ?PlanVersion
    {
        $model = PlanVersionModel::query()
            ->where('plan_id', $planId->value())
            ->where('version', $version)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function nextVersionNumber(Uuid $planId): int
    {
        $max = PlanVersionModel::query()
            ->where('plan_id', $planId->value())
            ->max('version');

        return ((int) $max) + 1;
    }

    public function save(PlanVersion $planVersion): void
    {
        PlanVersionModel::query()->updateOrCreate(
            ['id' => $planVersion->id()->value()],
            [
                'plan_id' => $planVersion->planId()->value(),
                'version' => $planVersion->version(),
                'price' => $planVersion->price()->amount() / 100,
                'currency' => $planVersion->price()->currency(),
                'billing_cycle' => $planVersion->billingCycle()->value,
                'trial_days' => $planVersion->trialDays(),
                'status' => $planVersion->status()->value,
                'created_at' => $planVersion->createdAt(),
            ],
        );
    }

    private function toDomain(PlanVersionModel $model): PlanVersion
    {
        return new PlanVersion(
            id: Uuid::fromString($model->id),
            planId: Uuid::fromString($model->plan_id),
            version: (int) $model->version,
            price: new Money((int) round((float) $model->price * 100), $model->currency),
            billingCycle: BillingCycle::from($model->billing_cycle),
            trialDays: (int) $model->trial_days,
            status: PlanStatus::from($model->status),
            createdAt: new DateTimeImmutable((string) $model->created_at),
        );
    }
}
