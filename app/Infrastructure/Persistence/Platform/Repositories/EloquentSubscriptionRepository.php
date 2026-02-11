<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\SubscriptionModel;
use Application\Billing\Contracts\SubscriptionRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\Subscription;
use Domain\Billing\Enums\BillingCycle;
use Domain\Billing\Enums\SubscriptionStatus;
use Domain\Billing\ValueObjects\BillingPeriod;
use Domain\Shared\ValueObjects\Uuid;

class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findById(Uuid $id): ?Subscription
    {
        $model = SubscriptionModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findActiveByTenantId(Uuid $tenantId): ?Subscription
    {
        $model = SubscriptionModel::query()
            ->where('tenant_id', $tenantId->value())
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
                SubscriptionStatus::PastDue->value,
                SubscriptionStatus::GracePeriod->value,
            ])
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<Subscription>
     */
    public function findByTenantId(Uuid $tenantId): array
    {
        return SubscriptionModel::query()
            ->where('tenant_id', $tenantId->value())
            ->get()
            ->map(fn (SubscriptionModel $model) => $this->toDomain($model))
            ->all();
    }

    /**
     * @return array<Subscription>
     */
    public function findDueForRenewal(DateTimeImmutable $now): array
    {
        return SubscriptionModel::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('current_period_end', '<=', $now->format('Y-m-d H:i:s'))
            ->get()
            ->map(fn (SubscriptionModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(Subscription $subscription): void
    {
        SubscriptionModel::query()->updateOrCreate(
            ['id' => $subscription->id()->value()],
            [
                'tenant_id' => $subscription->tenantId()->value(),
                'plan_version_id' => $subscription->planVersionId()->value(),
                'status' => $subscription->status()->value,
                'billing_cycle' => $subscription->billingCycle()->value,
                'current_period_start' => $subscription->currentPeriod()->start(),
                'current_period_end' => $subscription->currentPeriod()->end(),
                'grace_period_end' => $subscription->gracePeriodEnd(),
                'canceled_at' => $subscription->canceledAt(),
            ],
        );
    }

    private function toDomain(SubscriptionModel $model): Subscription
    {
        return new Subscription(
            id: Uuid::fromString($model->id),
            tenantId: Uuid::fromString($model->tenant_id),
            planVersionId: Uuid::fromString($model->plan_version_id),
            status: SubscriptionStatus::from($model->status),
            billingCycle: BillingCycle::from($model->billing_cycle),
            currentPeriod: new BillingPeriod(
                new DateTimeImmutable((string) $model->current_period_start),
                new DateTimeImmutable((string) $model->current_period_end),
            ),
            gracePeriodEnd: $model->grace_period_end
                ? new DateTimeImmutable((string) $model->grace_period_end)
                : null,
            canceledAt: $model->canceled_at
                ? new DateTimeImmutable((string) $model->canceled_at)
                : null,
        );
    }
}
