<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Auth;

use App\Interface\Http\Requests\Auth\RefreshTokenRequest;
use App\Interface\Http\Requests\Auth\TenantLoginRequest;
use App\Interface\Http\Resources\Auth\AuthTokensResource;
use App\Interface\Http\Resources\Auth\MfaRequiredResource;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\RefreshRequestDTO;
use Application\Auth\DTOs\TenantLoginRequestDTO;
use Application\Auth\UseCases\TenantLogin;
use Application\Auth\UseCases\TenantLogout;
use Application\Auth\UseCases\TenantRefreshAccessToken;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Illuminate\Http\JsonResponse;

class TenantAuthController
{
    public function login(TenantLoginRequest $request, TenantLogin $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new TenantLoginRequestDTO(
                email: $request->validated('email'),
                password: $request->validated('password'),
                tenantSlug: $request->validated('tenant_slug'),
                ipAddress: $request->ip() ?? '0.0.0.0',
                userAgent: $request->userAgent() ?? 'unknown',
            ));
        } catch (AuthenticationException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 401);
        }

        if ($result instanceof AuthTokensDTO) {
            return (new AuthTokensResource($result))
                ->response()
                ->setStatusCode(200);
        }

        return (new MfaRequiredResource($result))
            ->response()
            ->setStatusCode(200);
    }

    public function refresh(RefreshTokenRequest $request, TenantRefreshAccessToken $useCase): JsonResponse
    {
        /** @var TokenClaims $claims */
        $claims = app(TokenClaims::class);

        if ($claims->tenantId === null) {
            return new JsonResponse([
                'error' => 'INVALID_TOKEN',
                'message' => 'Token does not contain tenant context',
            ], 401);
        }

        try {
            $result = $useCase->execute(new RefreshRequestDTO(
                refreshToken: $request->validated('refresh_token'),
                ipAddress: $request->ip() ?? '0.0.0.0',
                userAgent: $request->userAgent() ?? 'unknown',
            ), $claims->tenantId);
        } catch (AuthenticationException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 401);
        }

        return (new AuthTokensResource($result))
            ->response()
            ->setStatusCode(200);
    }

    public function logout(TenantLogout $useCase): JsonResponse
    {
        /** @var TokenClaims $claims */
        $claims = app(TokenClaims::class);

        $useCase->execute($claims);

        return new JsonResponse(['message' => 'Successfully logged out'], 200);
    }
}
