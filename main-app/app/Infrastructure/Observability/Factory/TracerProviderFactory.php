<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Factory;

use App\Infrastructure\Observability\Config\OpenTelemetryConfig;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

final class TracerProviderFactory
{
    public function create(OpenTelemetryConfig $config, ResourceInfo $resource): TracerProvider
    {
        $transport = (new OtlpHttpTransportFactory())->create(
            $config->endpoint . '/v1/traces',
            'application/x-protobuf'
        );

        $exporter  = new SpanExporter($transport);
        $processor = new SimpleSpanProcessor($exporter);
        $sampler   = new ParentBased(new TraceIdRatioBasedSampler($config->samplerRatio));

        return TracerProvider::builder()
            ->addSpanProcessor($processor)
            ->setResource($resource)
            ->setSampler($sampler)
            ->build();
    }
}
