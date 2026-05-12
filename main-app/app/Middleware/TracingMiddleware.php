<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Hyperf\Tracer\SpanStarter;
use Hyperf\Tracer\TracerContext;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Hyperf\Coroutine\defer;
use const OpenTracing\Formats\TEXT_MAP;

class TracingMiddleware implements MiddlewareInterface
{
    use SpanStarter;

    public function __construct(protected ContainerInterface $container) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $operationName = $request->getMethod() . ' ' . $request->getUri()->getPath();

        $tracer = TracerContext::getTracer();

        defer(function () use ($tracer) {
            try { $tracer->flush(); } catch (\Throwable) {}
        });

        $spanContext = null;
        try {
            $spanContext = $tracer->extract(TEXT_MAP, $request->getHeaders());
        } catch (\Throwable) {}

        $span = $this->startSpan($operationName, ['child_of' => $spanContext]);
        $span->setTag('http.method', $request->getMethod());
        $span->setTag('http.url', (string) $request->getUri());
        $span->setTag('correlation_id', Context::get('correlation_id', 'n/a'));

        // CRITICO: registra como root para SpanStarter (annotations, DbAspect,
        // RedisAspect, etc.) anexarem spans subsequentes como filhos deste.
        TracerContext::setRoot($span);

        $carrier = [];
        $tracer->inject($span->getContext(), TEXT_MAP, $carrier);
        $traceId = $carrier['X-B3-Trace-ID'] ?? $carrier['x-b3-trace-iD'] ?? 'n/a';
        TracerContext::setTraceId((string) $traceId);

        // Mantidos pra compatibilidade com codigo legado que le essas chaves.
        Context::set('tracer.root_span', $span);
        Context::set('tracer.id', (string) $traceId);

        try {
            $response = $handler->handle($request);
            $span->setTag('http.status_code', $response->getStatusCode());
            return $response;
        } catch (\Throwable $throwable) {
            $span->setTag('error', true);
            $span->log([
                'event' => 'error',
                'message' => $throwable->getMessage(),
                'stack' => $throwable->getTraceAsString(),
            ]);
            throw $throwable;
        } finally {
            $span->finish();
        }
    }
}
