<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Infrastructure\Auth\AuthenticatedUser;
use App\Infrastructure\MultiTenancy\TenantContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TenantContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        if (app()->bound('correlation_id')) {
            $extra['correlation_id'] = app('correlation_id');
            $extra['trace_id'] = app('correlation_id');
        }

        if (app()->bound(TenantContext::class)) {
            $context = app(TenantContext::class);
            $extra['tenant_id'] = $context->tenantId;
        }

        if (app()->bound(AuthenticatedUser::class)) {
            $user = app(AuthenticatedUser::class);
            $extra['user_id'] = $user->userId->value();
        }

        return $record->with(extra: $extra);
    }
}
