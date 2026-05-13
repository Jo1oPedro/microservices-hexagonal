<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'enabled'           => (bool) env('OTEL_ENABLED', true),
    'service_name'      => (string) env('OTEL_SERVICE_NAME', 'main-app'),
    'service_namespace' => (string) env('OTEL_SERVICE_NAMESPACE', 'cascata'),
    'service_version'   => (string) env('OTEL_SERVICE_VERSION', '1.0.0'),
    'deployment_env'    => (string) env('OTEL_DEPLOYMENT_ENV', 'dev'),
    'endpoint'          => (string) env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
    'sampler_ratio'     => (float) env('OTEL_TRACES_SAMPLER_ARG', 1.0),
];