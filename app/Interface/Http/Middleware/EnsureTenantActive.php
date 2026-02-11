<?php

declare(strict_types=1);

namespace App\Interface\Http\Middleware;

use App\Infrastructure\MultiTenancy\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        if ($context->tenantStatus === 'suspended') {
            return new JsonResponse([
                'error' => 'tenant_suspended',
                'message' => 'This tenant account has been suspended. Please contact support.',
            ], 403);
        }

        if ($context->tenantStatus === 'canceled') {
            return new JsonResponse([
                'error' => 'tenant_canceled',
                'message' => 'This tenant account has been canceled.',
            ], 403);
        }

        $activeStatuses = ['active', 'trial', 'past_due'];
        if (! in_array($context->tenantStatus, $activeStatuses, true)) {
            return new JsonResponse([
                'error' => 'tenant_inactive',
                'message' => 'This tenant account is not active.',
            ], 403);
        }

        return $next($request);
    }
}
