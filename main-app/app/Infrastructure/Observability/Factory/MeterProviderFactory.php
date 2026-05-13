<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Factory;

use App\Infrastructure\Observability\Config\OpenTelemetryConfig;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;

final class MeterProviderFactory
{
    public function create(OpenTelemetryConfig $config, ResourceInfo $resource): MeterProvider
    {
        $transport = (new OtlpHttpTransportFactory())->create(
            $config->endpoint . '/v1/metrics',
            'application/x-protobuf'
        );

        $exporter = new MetricExporter($transport);
        $reader   = new ExportingReader($exporter);

        return MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();
    }
}
