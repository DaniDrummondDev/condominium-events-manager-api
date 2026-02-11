<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Auth;

use App\Interface\Http\Resources\Auth\AuthTokensResource;
use Application\Auth\DTOs\MfaVerifyRequestDTO;
use Application\Auth\UseCases\ConfirmMfaSetup;
use Application\Auth\UseCases\SetupMfa;
use Application\Auth\UseCases\VerifyMfa;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController
{
    public function setup(SetupMfa $useCase): JsonResponse
    {
        /** @var TokenClaims $claims */
        $claims = app(TokenClaims::class);

        try {
            $result = $useCase->execute($claims);
        } catch (AuthenticationException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse([
            'secret' => $result->secret,
            'otpauth_uri' => $result->otpauthUri,
            'recovery_codes' => $result->recoveryCodes,
        ]);
    }

    public function confirmSetup(Request $request, ConfirmMfaSetup $useCase): JsonResponse
    {
        $request->validate([
            'secret' => 'required|string',
            'code' => 'required|string|digits:6',
        ]);

        /** @var TokenClaims $claims */
        $claims = app(TokenClaims::class);

        try {
            $useCase->execute(
                $claims,
                $request->input('secret'),
                $request->input('code'),
            );
        } catch (AuthenticationException $e) {
            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse(['message' => 'MFA enabled successfully']);
    }

    public function verify(Request $request, VerifyMfa $useCase): JsonResponse
    {
        $request->validate([
            'mfa_token' => 'required|string',
            'code' => 'required|string|digits:6',
        ]);

        try {
            $result = $useCase->execute(new MfaVerifyRequestDTO(
                mfaToken: $request->input('mfa_token'),
                code: $request->input('code'),
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
}
