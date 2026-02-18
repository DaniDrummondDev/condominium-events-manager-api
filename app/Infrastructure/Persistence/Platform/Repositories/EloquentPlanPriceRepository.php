<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\PlanPriceModel;
use Application\Billing\Contracts\PlanPriceRepositoryInterface;
use Domain\Billing\Entities\PlanPrice;
use Domain\Billing\Enums\BillingCycle;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class EloquentPlanPriceRepository implements PlanPriceRepositoryInterface
{
    public function findByPlanVersionId(Uuid $planVersionId): array
    {
        return PlanPriceModel::query()
            ->where('plan_version_id', $planVersionId->value())
            ->get()
            ->map(fn (PlanPriceModel $model) => $this->toDomain($model))
            ->all();
    }

    public function findByPlanVersionIdAndBillingCycle(Uuid $planVersionId, BillingCycle $billingCycle): ?PlanPrice
    {
        $model = PlanPriceModel::query()
            ->where('plan_version_id', $planVersionId->value())
            ->where('billing_cycle', $billingCycle->value)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(PlanPrice $planPrice): void
    {
        PlanPriceModel::query()->updateOrCreate(
            ['id' => $planPrice->id()->value()],
            [
                'plan_version_id' => $planPrice->planVersionId()->value(),
                'billing_cycle' => $planPrice->billingCycle()->value,
                'price' => $planPrice->price()->amount() / 100,
                'currency' => $planPrice->price()->currency(),
                'trial_days' => $planPrice->trialDays(),
            ],
        );
    }

    public function saveMany(array $planPrices): void
    {
        foreach ($planPrices as $planPrice) {
            $this->save($planPrice);
        }
    }

    private function toDomain(PlanPriceModel $model): PlanPrice
    {
        return new PlanPrice(
            id: Uuid::fromString($model->id),
            planVersionId: Uuid::fromString($model->plan_version_id),
            billingCycle: BillingCycle::from($model->billing_cycle),
            price: new Money((int) round((float) $model->price * 100), $model->currency),
            trialDays: (int) $model->trial_days,
        );
    }
}
