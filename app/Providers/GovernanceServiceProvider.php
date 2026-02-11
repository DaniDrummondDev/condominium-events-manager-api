<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Persistence\Tenant\Repositories\EloquentCondominiumRuleRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentPenaltyPolicyRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentPenaltyRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentViolationContestationRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentViolationRepository;
use Application\Governance\Contracts\CondominiumRuleRepositoryInterface;
use Application\Governance\Contracts\PenaltyPolicyRepositoryInterface;
use Application\Governance\Contracts\PenaltyRepositoryInterface;
use Application\Governance\Contracts\ViolationContestationRepositoryInterface;
use Application\Governance\Contracts\ViolationRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class GovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CondominiumRuleRepositoryInterface::class, EloquentCondominiumRuleRepository::class);
        $this->app->bind(ViolationRepositoryInterface::class, EloquentViolationRepository::class);
        $this->app->bind(ViolationContestationRepositoryInterface::class, EloquentViolationContestationRepository::class);
        $this->app->bind(PenaltyRepositoryInterface::class, EloquentPenaltyRepository::class);
        $this->app->bind(PenaltyPolicyRepositoryInterface::class, EloquentPenaltyPolicyRepository::class);
    }
}
