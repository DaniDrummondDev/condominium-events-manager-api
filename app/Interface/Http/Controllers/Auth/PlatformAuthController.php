<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Auth;

use App\Interface\Http\Requests\Auth\PlatformLoginRequest;
use App\Interface\Http\Requests\Auth\RefreshTokenRequest;
use App\Interface\Http\Resources\Auth\AuthTokensResource;
use App\Interface\Http\Resources\Auth\MfaRequiredResource;
use Application\Auth\DTOs\AuthTokensDTO;
use Application\Auth\DTOs\LoginRequestDTO;
use Application\Auth\DTOs\RefreshRequestDTO;
use Application\Auth\UseCases\PlatformLogin;
use Application\Auth\UseCases\PlatformLogout;
use Application\Auth\UseCases\RefreshAccessToken;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Illuminate\Http\JsonResponse;

class PlatformAuthController
{
    public function login(PlatformLoginRequest $request, PlatformLogin $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new LoginRequestDTO(
                email: $request->validated('email'),
                password: $request->validated('password'),
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

    public function refresh(RefreshTokenRequest $request, RefreshAccessToken $useCase): JsonResponse
    {
        try {
            $result = $useCase->execute(new RefreshRequestDTO(
                refreshToken: $request->validated('refresh_token'),
                ipAddress: $request->ip() ?? '0.0.0.0',
                userAgent: $request->userAgent() ?? 'unknown',
            ));
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

    public function logout(PlatformLogout $useCase): JsonResponse
    {
        /** @var TokenClaims $claims */
        $claims = app(TokenClaims::class);

        $useCase->execute($claims);

        return new JsonResponse(['message' => 'Successfully logged out'], 200);
    }
}
