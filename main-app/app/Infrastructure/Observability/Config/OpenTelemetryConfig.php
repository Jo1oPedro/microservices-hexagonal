<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Config;

use Hyperf\Contract\ConfigInterface;

final readonly class OpenTelemetryConfig
{
    public function __construct(
        public bool $enabled,
        public string $serviceName,
        public string $serviceNamespace,
        public string $serviceVersion,
        public string $deploymentEnv,
        public string $endpoint,
        public float $samplerRatio,
    ) {}

    public static function fromHyperfConfig(ConfigInterface $config): self
    {
        return new self(
            enabled:          (bool) $config->get('opentelemetry.enabled', true),
            serviceName:      (string) $config->get('opentelemetry.service_name', 'main-app'),
            serviceNamespace: (string) $config->get('opentelemetry.service_namespace', 'cascata'),
            serviceVersion:   (string) $config->get('opentelemetry.service_version', '1.0.0'),
            deploymentEnv:    (string) $config->get('opentelemetry.deployment_env', 'dev'),
            endpoint:         rtrim((string) $config->get('opentelemetry.endpoint', 'http://otel-collector:4318'), '/'),
            samplerRatio:     (float) $config->get('opentelemetry.sampler_ratio', 1.0),
        );
    }
}
