<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Notifications\EmailNotificationAdapter;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentBlockRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentResidentRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentUnitRepository;
use Application\Shared\Contracts\NotificationServiceInterface;
use Application\Unit\Contracts\BlockRepositoryInterface;
use Application\Unit\Contracts\ResidentRepositoryInterface;
use Application\Unit\Contracts\UnitRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class UnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BlockRepositoryInterface::class, EloquentBlockRepository::class);
        $this->app->bind(UnitRepositoryInterface::class, EloquentUnitRepository::class);
        $this->app->bind(ResidentRepositoryInterface::class, EloquentResidentRepository::class);
        $this->app->bind(NotificationServiceInterface::class, EmailNotificationAdapter::class);
    }
}
