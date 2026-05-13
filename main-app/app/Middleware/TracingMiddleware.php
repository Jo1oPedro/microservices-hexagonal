<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TracingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $tracer = Globals::tracerProvider()->getTracer('main-app.http');

        $propagator = Globals::propagator();
        $parentContext = $propagator->extract($request->getHeaders());

        $span = $tracer->spanBuilder(sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()))
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($parentContext)
            ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
            ->setAttribute(TraceAttributes::URL_PATH, $request->getUri()->getPath())
            ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
            ->startSpan();

        $spanContext = $span->getContext();
        Context::set('otel.trace_id', $spanContext->getTraceId());
        Context::set('otel.span_id', $spanContext->getSpanId());

        $scope = $span->activate();

        try {
            $response = $handler->handle($request);
            $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }
            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}