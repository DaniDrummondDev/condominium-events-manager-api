<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Infrastructure\EventListeners\InvalidateDashboardCache;
use App\Infrastructure\EventListeners\InvalidateSpaceAvailabilityCache;
use Domain\Reservation\Events\ReservationCanceled;
use Domain\Reservation\Events\ReservationConfirmed;
use Domain\Reservation\Events\ReservationRequested;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerEventListeners();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('auth-refresh', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('billing-webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('tenant-api', function (Request $request) {
            $tenantId = $this->extractTenantId($request);

            return Limit::perMinute(1000)->by($tenantId ?? ($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('mutation', function (Request $request) {
            $userId = $this->extractUserId($request);

            return Limit::perMinute(30)->by($userId ?? ($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('ai', function (Request $request) {
            $tenantId = $this->extractTenantId($request);
            $userId = $this->extractUserId($request);

            $key = $userId !== null
                ? "{$tenantId}:{$userId}"
                : ($tenantId ?? ($request->ip() ?? 'unknown'));

            return Limit::perMinute(20)->by($key);
        });
    }

    private function registerEventListeners(): void
    {
        $reservationEvents = [
            ReservationRequested::class,
            ReservationConfirmed::class,
            ReservationCanceled::class,
        ];

        // Space availability: only ReservationRequested carries startDatetime/endDatetime
        Event::listen(ReservationRequested::class, InvalidateSpaceAvailabilityCache::class);

        // Dashboard: all reservation events (uses only tenantId from context)
        Event::listen($reservationEvents, InvalidateDashboardCache::class);
    }

    private function extractTenantId(Request $request): ?string
    {
        if ($this->app->bound(AuthenticatedUser::class)) {
            $user = $this->app->make(AuthenticatedUser::class);

            return $user->tenantId?->value();
        }

        return null;
    }

    private function extractUserId(Request $request): ?string
    {
        if ($this->app->bound(AuthenticatedUser::class)) {
            $user = $this->app->make(AuthenticatedUser::class);

            return $user->userId->value();
        }

        return null;
    }
}
