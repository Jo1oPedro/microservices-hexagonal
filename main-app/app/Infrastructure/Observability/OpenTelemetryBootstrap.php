<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Infrastructure\Observability\Config\OpenTelemetryConfig;
use App\Infrastructure\Observability\Factory\LoggerProviderFactory;
use App\Infrastructure\Observability\Factory\MeterProviderFactory;
use App\Infrastructure\Observability\Factory\TracerProviderFactory;
use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

final class OpenTelemetryBootstrap
{
    private bool $registered = false;
    private ?TracerProvider $tracerProvider = null;
    private ?MeterProvider $meterProvider = null;
    private ?LoggerProvider $loggerProvider = null;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TracerProviderFactory $tracerFactory,
        private readonly MeterProviderFactory $meterFactory,
        private readonly LoggerProviderFactory $loggerFactory,
    ) {}

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $cfg = OpenTelemetryConfig::fromHyperfConfig($this->config);
        if (! $cfg->enabled) {
            return;
        }

        $resource = $this->buildResource($cfg);

        $this->tracerProvider = $this->tracerFactory->create($cfg, $resource);
        $this->meterProvider  = $this->meterFactory->create($cfg, $resource);
        $this->loggerProvider = $this->loggerFactory->create($cfg, $resource);

        Sdk::builder()
            ->setTracerProvider($this->tracerProvider)
            ->setMeterProvider($this->meterProvider)
            ->setLoggerProvider($this->loggerProvider)
            ->setPropagator($this->buildPropagator())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        $this->registered = true;
    }

    public function loggerProvider(): ?LoggerProvider
    {
        return $this->loggerProvider;
    }

    private function buildResource(OpenTelemetryConfig $cfg): ResourceInfo
    {
        return ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME           => $cfg->serviceName,
                ResourceAttributes::SERVICE_NAMESPACE      => $cfg->serviceNamespace,
                ResourceAttributes::SERVICE_VERSION        => $cfg->serviceVersion,
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $cfg->deploymentEnv,
            ]))
        );
    }

    private function buildPropagator(): TextMapPropagatorInterface
    {
        return new MultiTextMapPropagator([
            TraceContextPropagator::getInstance(),
            BaggagePropagator::getInstance(),
        ]);
    }
}
