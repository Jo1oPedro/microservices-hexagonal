<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Logging;

use Hyperf\Context\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;

final class OtelTraceContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $traceId = Context::get('otel.trace_id');
        $spanId  = Context::get('otel.span_id');

        // 2) Fallback: span ativo no momento
        if ($traceId === null) {
            $ctx = Span::getCurrent()->getContext();
            if ($ctx->isValid()) {
                $traceId = $ctx->getTraceId();
                $spanId  = $ctx->getSpanId();
            }
        }

        if ($traceId !== null) {
            $record->extra['trace_id'] = $traceId;
            $record->extra['span_id']  = $spanId;
        }

        return $record;

        /*$ctx = Span::getCurrent()->getContext();
        if (! $ctx->isValid()) {
            return $record;
        }

        $record->extra['trace_id'] = $ctx->getTraceId();
        $record->extra['span_id']  = $ctx->getSpanId();

        return $record;*/
    }
}
