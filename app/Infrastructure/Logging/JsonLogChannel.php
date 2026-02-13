<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class JsonLogChannel
{
    public function __invoke(array $config): Logger
    {
        $handler = new StreamHandler(
            $config['path'] ?? storage_path('logs/laravel.json'),
            $config['level'] ?? 'debug',
        );

        $handler->setFormatter(new JsonFormatter());

        $logger = new Logger('json');
        $logger->pushHandler($handler);
        $logger->pushProcessor(new TenantContextProcessor());

        return $logger;
    }
}
