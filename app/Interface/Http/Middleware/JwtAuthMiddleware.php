<?php

declare(strict_types=1);

namespace App\Interface\Http\Middleware;

use App\Infrastructure\Auth\AuthenticatedUser;
use Application\Auth\Contracts\TokenValidatorInterface;
use Closure;
use Domain\Auth\Exceptions\AuthenticationException;
use Domain\Auth\ValueObjects\TokenClaims;
use Domain\Auth\ValueObjects\TokenType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function __construct(
        private readonly TokenValidatorInterface $tokenValidator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return new JsonResponse([
                'error' => 'unauthenticated',
                'message' => 'Authentication token is required',
            ], 401);
        }

        try {
            $claims = $this->tokenValidator->validate($token);
        } catch (AuthenticationException $e) {
            return new JsonResponse([
                'error' => 'unauthenticated',
                'message' => $e->getMessage(),
            ], 401);
        }

        // Only accept access tokens (not MFA tokens)
        if ($claims->tokenType !== TokenType::Access) {
            return new JsonResponse([
                'error' => 'unauthenticated',
                'message' => 'Invalid token type',
            ], 401);
        }

        // Store claims on request for downstream use
        $request->attributes->set('token_claims', $claims);

        // Bind AuthenticatedUser as singleton for this request
        $authenticatedUser = AuthenticatedUser::fromClaims($claims);
        app()->instance(AuthenticatedUser::class, $authenticatedUser);
        app()->instance(TokenClaims::class, $claims);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);

        return $token !== '' ? $token : null;
    }
}
