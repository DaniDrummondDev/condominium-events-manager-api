<?php

declare(strict_types=1);

namespace App\Interface\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggingMiddleware
{
    private const int PAYLOAD_MAX_LENGTH = 1000;

    /** @var list<string> */
    private const array SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'credit_card',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $level = $response->getStatusCode() >= 500 ? 'error' : 'info';

        Log::log($level, 'HTTP Request', [
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $this->sanitizePayload($request->all()),
        ]);

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = array_diff_key($payload, array_flip(self::SENSITIVE_FIELDS));

        $json = (string) json_encode($sanitized);

        if (strlen($json) > self::PAYLOAD_MAX_LENGTH) {
            return ['_truncated' => true, '_size' => strlen($json)];
        }

        return $sanitized;
    }
}
