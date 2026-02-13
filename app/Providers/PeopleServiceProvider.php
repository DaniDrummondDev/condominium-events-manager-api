<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Persistence\Tenant\Repositories\EloquentGuestRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentServiceProviderRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentServiceProviderVisitRepository;
use Application\People\Contracts\GuestRepositoryInterface;
use Application\People\Contracts\ServiceProviderRepositoryInterface;
use Application\People\Contracts\ServiceProviderVisitRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class PeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GuestRepositoryInterface::class, EloquentGuestRepository::class);
        $this->app->bind(ServiceProviderRepositoryInterface::class, EloquentServiceProviderRepository::class);
        $this->app->bind(ServiceProviderVisitRepositoryInterface::class, EloquentServiceProviderVisitRepository::class);
    }
}
