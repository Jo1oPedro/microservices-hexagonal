<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Tracing\Aspect;

use App\Infrastructure\Observability\Tracing\Annotation\Traced;
use Hyperf\Context\Context;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

final class TracedAspect extends AbstractAspect
{
    public array $annotations = [Traced::class];

    public function process(ProceedingJoinPoint $proceedingJoinPoint): mixed
    {
        /** @var Traced $annotation */
        $annotation = $proceedingJoinPoint->getAnnotationMetadata()->method[Traced::class];

        $spanName = $annotation->name
            ?? $this->shortClass($proceedingJoinPoint->className) . '::' . $proceedingJoinPoint->methodName;

        $tracer = Globals::tracerProvider()->getTracer('main-app.app');

        $span = $tracer->spanBuilder($spanName)
            ->setSpanKind($this->resolveKind($annotation->kind))
            ->setAttribute('code.namespace', $proceedingJoinPoint->className)
            ->setAttribute('code.function', $proceedingJoinPoint->methodName)
            ->startSpan();

        $scope = $span->activate();
        $previousSpanId = Context::get('otel.span_id');
        Context::set('otel.span_id', $span->getContext()->getSpanId());

        try {
            return $proceedingJoinPoint->process();
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
            if ($previousSpanId !== null) {
                Context::set('otel.span_id', $previousSpanId);
            }
        }
    }

    private function resolveKind(string $kind): int
    {
        return match (strtolower($kind)) {
            'server'   => SpanKind::KIND_SERVER,
            'client'   => SpanKind::KIND_CLIENT,
            'producer' => SpanKind::KIND_PRODUCER,
            'consumer' => SpanKind::KIND_CONSUMER,
            default    => SpanKind::KIND_INTERNAL,
        };
    }

    private function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
