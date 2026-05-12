<?php

declare(strict_types=1);

namespace App\Tracing;

use PhpAmqpLib\Message\AMQPMessage;

/**
 * Reporter Zipkin v2 minimalista — só o suficiente para emitir 1 span
 * por mensagem consumida, herdando o trace do publisher via B3 headers.
 */
final class ZipkinTracer
{
    private string $endpoint;
    private string $serviceName;

    public function __construct(?string $endpoint = null, ?string $serviceName = null)
    {
        $this->endpoint    = $endpoint    ?? ($_ENV['ZIPKIN_ENDPOINT_URL'] ?? 'http://jaeger:9411/api/v2/spans');
        $this->serviceName = $serviceName ?? ($_ENV['APP_NAME']            ?? 'send-email-worker');
    }

    public function startFromMessage(AMQPMessage $message, string $operationName): Span
    {
        $headers = $this->extractHeaders($message);
        $sampled = ($headers['x-b3-sampled'] ?? '1') === '1';

        $traceId      = $headers['x-b3-traceid']      ?? $this->randomHex(32);
        $parentSpanId = $headers['x-b3-spanid']       ?? null;
        $spanId       = $this->randomHex(16);

        return new Span(
            tracer:       $this,
            traceId:      $traceId,
            spanId:       $spanId,
            parentSpanId: $parentSpanId,
            name:         $operationName,
            sampled:      $sampled,
        );
    }

    public function report(Span $span): void
    {
        if (!$span->sampled) {
            return;
        }

        $payload = [[
            'id'             => $span->spanId,
            'traceId'        => $span->traceId,
            'parentId'       => $span->parentSpanId,
            'name'           => $span->name,
            'kind'           => 'CONSUMER',
            'timestamp'      => $span->startMicros,
            'duration'       => max(1, (int) (microtime(true) * 1_000_000) - $span->startMicros),
            'localEndpoint'  => ['serviceName' => $this->serviceName],
            'tags'           => $span->tags,
        ]];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(array_map(
                static fn($s) => array_filter($s, static fn($v) => $v !== null && $v !== []),
                $payload
            )),
            CURLOPT_TIMEOUT_MS     => 1000,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function extractHeaders(AMQPMessage $message): array
    {
        $props = $message->get_properties();
        $raw   = ($props['application_headers'] ?? null)?->getNativeData() ?? [];

        // Normaliza pra lower-case
        $out = [];
        foreach ($raw as $k => $v) {
            $out[strtolower((string) $k)] = is_scalar($v) ? (string) $v : $v;
        }
        return $out;
    }

    private function randomHex(int $len): string
    {
        return bin2hex(random_bytes((int) ($len / 2)));
    }
}
