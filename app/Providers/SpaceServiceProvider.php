<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Persistence\Tenant\Repositories\EloquentSpaceAvailabilityRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentSpaceBlockRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentSpaceRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentSpaceRuleRepository;
use Application\Space\Contracts\SpaceAvailabilityRepositoryInterface;
use Application\Space\Contracts\SpaceBlockRepositoryInterface;
use Application\Space\Contracts\SpaceRepositoryInterface;
use Application\Space\Contracts\SpaceRuleRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class SpaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpaceRepositoryInterface::class, EloquentSpaceRepository::class);
        $this->app->bind(SpaceAvailabilityRepositoryInterface::class, EloquentSpaceAvailabilityRepository::class);
        $this->app->bind(SpaceBlockRepositoryInterface::class, EloquentSpaceBlockRepository::class);
        $this->app->bind(SpaceRuleRepositoryInterface::class, EloquentSpaceRuleRepository::class);
    }
}
