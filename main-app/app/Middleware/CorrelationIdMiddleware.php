<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

class CorrelationIdMiddleware implements MiddlewareInterface
{
    public const HEADER = "X-correlation-ID";
    public const CONTEXT_KEY = "correlation_id";

    public function __construct(protected ContainerInterface $container) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $correlationId = $request->getHeaderLine(self::HEADER) ?: Uuid::uuid4()->toString();
        Context::set(self::CONTEXT_KEY, $correlationId);
        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER, $correlationId);
    }
}
