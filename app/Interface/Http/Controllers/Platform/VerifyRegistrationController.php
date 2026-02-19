<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Interface\Http\Resources\Platform\TenantRegistrationResource;
use Application\Tenant\UseCases\VerifyRegistration;
use Domain\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyRegistrationController
{
    public function __invoke(
        Request $request,
        VerifyRegistration $useCase,
    ): JsonResponse {
        $token = $request->query('token');

        if (empty($token) || ! is_string($token)) {
            return new JsonResponse([
                'error' => 'VERIFICATION_TOKEN_REQUIRED',
                'message' => 'Verification token is required.',
            ], 400);
        }

        try {
            $tenant = $useCase->execute($token);

            return (new TenantRegistrationResource($tenant))
                ->response()
                ->setStatusCode(200);
        } catch (DomainException $e) {
            $statusCode = match ($e->errorCode()) {
                'VERIFICATION_TOKEN_INVALID' => 404,
                'VERIFICATION_TOKEN_EXPIRED' => 410,
                'TENANT_SLUG_ALREADY_EXISTS' => 409,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
