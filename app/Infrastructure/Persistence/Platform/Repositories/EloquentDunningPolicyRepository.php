<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\DunningPolicyModel;
use Application\Billing\Contracts\DunningPolicyRepositoryInterface;
use Domain\Billing\Entities\DunningPolicy;
use Domain\Shared\ValueObjects\Uuid;

class EloquentDunningPolicyRepository implements DunningPolicyRepositoryInterface
{
    public function findDefault(): ?DunningPolicy
    {
        $model = DunningPolicyModel::query()
            ->where('is_default', true)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findById(Uuid $id): ?DunningPolicy
    {
        $model = DunningPolicyModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function save(DunningPolicy $policy): void
    {
        DunningPolicyModel::query()->updateOrCreate(
            ['id' => $policy->id()->value()],
            [
                'name' => $policy->name(),
                'max_retries' => $policy->maxRetries(),
                'retry_intervals' => $policy->retryIntervals(),
                'suspend_after_days' => $policy->suspendAfterDays(),
                'cancel_after_days' => $policy->cancelAfterDays(),
                'is_default' => $policy->isDefault(),
            ],
        );
    }

    private function toDomain(DunningPolicyModel $model): DunningPolicy
    {
        return new DunningPolicy(
            id: Uuid::fromString($model->id),
            name: $model->name,
            maxRetries: (int) $model->max_retries,
            retryIntervals: array_map('intval', (array) $model->retry_intervals),
            suspendAfterDays: (int) $model->suspend_after_days,
            cancelAfterDays: (int) $model->cancel_after_days,
            isDefault: (bool) $model->is_default,
        );
    }
}
