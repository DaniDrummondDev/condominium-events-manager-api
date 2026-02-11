<?php

declare(strict_types=1);

namespace App\Interface\Http\Middleware;

use App\Infrastructure\MultiTenancy\TenantContext;
use App\Infrastructure\MultiTenancy\TenantManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantMiddleware
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->extractTenantId($request);

        if ($tenantId === null) {
            return new JsonResponse([
                'error' => 'tenant_required',
                'message' => 'Tenant identification is required',
            ], 403);
        }

        try {
            $context = $this->tenantManager->resolve($tenantId);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'error' => 'tenant_not_found',
                'message' => 'The specified tenant was not found',
            ], 403);
        }

        // Registra TenantContext no container para injecao de dependencia
        app()->instance(TenantContext::class, $context);

        $response = $next($request);

        // Limpa conexao apos a request
        $this->tenantManager->resetConnection();

        return $response;
    }

    private function extractTenantId(Request $request): ?string
    {
        // Por enquanto extrai do header X-Tenant-ID
        // Na Fase 2 (Auth) sera extraido do JWT claim tenant_id
        return $request->header('X-Tenant-ID');
    }
}
