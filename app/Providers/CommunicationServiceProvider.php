<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Persistence\Tenant\Repositories\EloquentAnnouncementReadRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentAnnouncementRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentSupportMessageRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentSupportRequestRepository;
use Application\Communication\Contracts\AnnouncementReadRepositoryInterface;
use Application\Communication\Contracts\AnnouncementRepositoryInterface;
use Application\Communication\Contracts\SupportMessageRepositoryInterface;
use Application\Communication\Contracts\SupportRequestRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnnouncementRepositoryInterface::class, EloquentAnnouncementRepository::class);
        $this->app->bind(AnnouncementReadRepositoryInterface::class, EloquentAnnouncementReadRepository::class);
        $this->app->bind(SupportRequestRepositoryInterface::class, EloquentSupportRequestRepository::class);
        $this->app->bind(SupportMessageRepositoryInterface::class, EloquentSupportMessageRepository::class);
    }
}
