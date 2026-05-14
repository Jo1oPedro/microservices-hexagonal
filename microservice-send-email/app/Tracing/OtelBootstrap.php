<?php

declare(strict_types=1);

namespace App\Tracing;

use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * Minimal framework-agnostic OTel bootstrap.
 *
 * Reads from environment variables and registers a global TracerProvider
 * + W3C TraceContext propagator. No metrics/logs providers — this worker
 * only needs traces.
 */
final class OtelBootstrap
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        if (! self::enabled()) {
            return;
        }

        $endpoint = rtrim((string) ($_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? 'http://otel-collector:4318'), '/');

        $transport = (new OtlpHttpTransportFactory())->create(
            $endpoint . '/v1/traces',
            'application/x-protobuf'
        );

        $exporter  = new SpanExporter($transport);
        $processor = new SimpleSpanProcessor($exporter);
        $sampler   = new ParentBased(new TraceIdRatioBasedSampler(
            (float) ($_ENV['OTEL_TRACES_SAMPLER_ARG'] ?? 1.0)
        ));

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor($processor)
            ->setResource(self::resource())
            ->setSampler($sampler)
            ->build();

        $propagator = new MultiTextMapPropagator([
            TraceContextPropagator::getInstance(),
            BaggagePropagator::getInstance(),
        ]);

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator($propagator)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        self::$registered = true;
    }

    private static function enabled(): bool
    {
        $v = $_ENV['OTEL_ENABLED'] ?? 'true';
        return ! in_array(strtolower((string) $v), ['0', 'false', 'no', 'off'], true);
    }

    private static function resource(): ResourceInfo
    {
        return ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME                => (string) ($_ENV['OTEL_SERVICE_NAME']      ?? 'send-email-worker'),
                ResourceAttributes::SERVICE_NAMESPACE           => (string) ($_ENV['OTEL_SERVICE_NAMESPACE'] ?? 'cascata'),
                ResourceAttributes::SERVICE_VERSION             => (string) ($_ENV['OTEL_SERVICE_VERSION']   ?? '1.0.0'),
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => (string) ($_ENV['OTEL_DEPLOYMENT_ENV']    ?? 'dev'),
            ]))
        );
    }
}
