<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Events\LaravelEventDispatcher;
use App\Infrastructure\MultiTenancy\TenantManager;
use App\Infrastructure\Notifications\EmailNotificationAdapter;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPendingRegistrationRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentTenantRepository;
use Application\Shared\Contracts\EventDispatcherInterface;
use Application\Shared\Contracts\NotificationServiceInterface;
use Application\Tenant\Contracts\PendingRegistrationRepositoryInterface;
use Application\Tenant\Contracts\TenantRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        TenantRepositoryInterface::class => EloquentTenantRepository::class,
        EventDispatcherInterface::class => LaravelEventDispatcher::class,
        PendingRegistrationRepositoryInterface::class => EloquentPendingRegistrationRepository::class,
        NotificationServiceInterface::class => EmailNotificationAdapter::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }
}
