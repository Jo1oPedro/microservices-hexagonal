<?php

declare(strict_types=1);

use Cascata\HyperfOpenTelemetry\Logging\OtelLogHandler;
use Cascata\HyperfOpenTelemetry\Logging\OtelTraceContextProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

return [
    'default' => [
        'handlers' => [
            [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => BASE_PATH . '/runtime/logs/hyperf.log',
                    'level'  => Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => JsonFormatter::class,
                    'constructor' => [],
                ],
            ],
            [
                'class' => OtelLogHandler::class,
                'constructor' => [
                    'level' => Logger::INFO,
                ],
            ],
        ],
        'processors' => [
            ['class' => OtelTraceContextProcessor::class],
        ],
    ],
];
