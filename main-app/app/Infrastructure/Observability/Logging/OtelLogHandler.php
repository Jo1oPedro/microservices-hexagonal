<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Logging;

use App\Infrastructure\Observability\OpenTelemetryBootstrap;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Logs\LogRecord as OtelLogRecord;
use OpenTelemetry\API\Logs\Severity;

final class OtelLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly OpenTelemetryBootstrap $bootstrap,
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $provider = $this->bootstrap->loggerProvider();
        if ($provider === null) {
            return;
        }

        $logger = $provider->getLogger('main-app.monolog');

        $otelRecord = (new OtelLogRecord($record->message))
            ->setSeverityNumber(Severity::fromPsr3($record->level->toPsrLogLevel()))
            ->setSeverityText($record->level->getName())
            ->setAttributes(array_merge($record->context, $record->extra))
            ->setTimestamp((int) ($record->datetime->format('U.u') * 1e9));

        $logger->emit($otelRecord);
    }
}
