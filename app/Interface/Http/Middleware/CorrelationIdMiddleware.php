<?php

declare(strict_types=1);

namespace App\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public const string HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header(self::HEADER) ?? (string) Str::uuid();

        $request->headers->set(self::HEADER, $correlationId);

        app()->instance('correlation_id', $correlationId);

        $response = $next($request);

        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
