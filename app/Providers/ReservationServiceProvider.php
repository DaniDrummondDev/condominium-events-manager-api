<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Persistence\Tenant\Repositories\EloquentReservationRepository;
use Application\Reservation\Contracts\ReservationRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class ReservationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReservationRepositoryInterface::class, EloquentReservationRepository::class);
    }
}
