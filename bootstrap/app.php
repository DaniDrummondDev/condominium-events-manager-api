<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('platform')
                ->group(base_path('routes/platform.php'));

            Route::middleware('api')
                ->prefix('tenant')
                ->group(base_path('routes/tenant.php'));

            Route::middleware('api')
                ->prefix('webhook')
                ->group(base_path('routes/webhook.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.resolve' => \App\Interface\Http\Middleware\ResolveTenantMiddleware::class,
            'tenant.active' => \App\Interface\Http\Middleware\EnsureTenantActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
