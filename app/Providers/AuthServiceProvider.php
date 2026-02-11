<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Auth\Audit\EloquentAuthAuditLogger;
use App\Infrastructure\Auth\BcryptPasswordHasher;
use App\Infrastructure\Auth\Jwt\LcobucciTokenIssuer;
use App\Infrastructure\Auth\Jwt\LcobucciTokenValidator;
use App\Infrastructure\Auth\Jwt\RedisTokenRevocation;
use App\Infrastructure\Auth\Mfa\Google2faTotpService;
use App\Infrastructure\Auth\TenantManagerAdapter;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPlatformRefreshTokenRepository;
use App\Infrastructure\Persistence\Platform\Repositories\EloquentPlatformUserRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentTenantRefreshTokenRepository;
use App\Infrastructure\Persistence\Tenant\Repositories\EloquentTenantUserRepository;
use Application\Auth\Contracts\AuthAuditLoggerInterface;
use Application\Auth\Contracts\PlatformUserRepositoryInterface;
use Application\Auth\Contracts\RefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TenantConnectionManagerInterface;
use Application\Auth\Contracts\TenantRefreshTokenRepositoryInterface;
use Application\Auth\Contracts\TenantUserRepositoryInterface;
use Application\Auth\Contracts\TokenIssuerInterface;
use Application\Auth\Contracts\TokenRevocationInterface;
use Application\Auth\Contracts\TokenValidatorInterface;
use Application\Auth\Contracts\TotpServiceInterface;
use Application\Auth\UseCases\PlatformLogin;
use Application\Auth\UseCases\RefreshAccessToken;
use Application\Auth\UseCases\TenantLogin;
use Application\Auth\UseCases\TenantRefreshAccessToken;
use Application\Auth\UseCases\VerifyMfa;
use Domain\Auth\Contracts\PasswordHasherInterface;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        TokenIssuerInterface::class => LcobucciTokenIssuer::class,
        TokenValidatorInterface::class => LcobucciTokenValidator::class,
        TokenRevocationInterface::class => RedisTokenRevocation::class,
        PasswordHasherInterface::class => BcryptPasswordHasher::class,
        PlatformUserRepositoryInterface::class => EloquentPlatformUserRepository::class,
        RefreshTokenRepositoryInterface::class => EloquentPlatformRefreshTokenRepository::class,
        TenantUserRepositoryInterface::class => EloquentTenantUserRepository::class,
        TenantRefreshTokenRepositoryInterface::class => EloquentTenantRefreshTokenRepository::class,
        TenantConnectionManagerInterface::class => TenantManagerAdapter::class,
        TotpServiceInterface::class => Google2faTotpService::class,
        AuthAuditLoggerInterface::class => EloquentAuthAuditLogger::class,
    ];

    public function register(): void
    {
        // Platform use case config injection
        $this->app->when(PlatformLogin::class)
            ->needs('$refreshTokenTtl')
            ->giveConfig('jwt.refresh_ttl');

        $this->app->when(RefreshAccessToken::class)
            ->needs('$refreshTokenTtl')
            ->giveConfig('jwt.refresh_ttl');

        // Tenant use case config injection
        $this->app->when(TenantLogin::class)
            ->needs('$refreshTokenTtl')
            ->giveConfig('jwt.refresh_ttl');

        $this->app->when(TenantRefreshAccessToken::class)
            ->needs('$refreshTokenTtl')
            ->giveConfig('jwt.refresh_ttl');

        $this->app->when(VerifyMfa::class)
            ->needs('$refreshTokenTtl')
            ->giveConfig('jwt.refresh_ttl');
    }
}
