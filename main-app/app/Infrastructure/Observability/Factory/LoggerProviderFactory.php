<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Factory;

use App\Infrastructure\Observability\Config\OpenTelemetryConfig;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;

final class LoggerProviderFactory
{
    public function create(OpenTelemetryConfig $config, ResourceInfo $resource): LoggerProvider
    {
        $transport = (new OtlpHttpTransportFactory())->create(
            $config->endpoint . '/v1/logs',
            'application/x-protobuf'
        );

        $exporter  = new LogsExporter($transport);
        $processor = new SimpleLogRecordProcessor($exporter);

        return LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($processor)
            ->build();
    }
}
