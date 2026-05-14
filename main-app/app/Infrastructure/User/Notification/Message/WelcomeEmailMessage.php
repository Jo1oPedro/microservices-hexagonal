<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Notification\Message;

use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Context\Context;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Context\Context as OtelContext;
use PhpAmqpLib\Wire\AMQPTable;

class WelcomeEmailMessage extends ProducerMessage
{
    protected string $exchange = 'notifications';
    protected string|array $routingKey = 'user.welcome_email';

    public function __construct(protected mixed $payload)
    {
        $this->properties['application_headers'] = new AMQPTable(
            $this->buildTracingHeaders()
        );
    }

    public function payload(): string
    {
        return json_encode(['email' => $this->payload]);
    }

    /**
     * Injects W3C TraceContext (traceparent + tracestate) into AMQP headers
     * so the consumer can continue the same trace.
     */
    private function buildTracingHeaders(): array
    {
        $headers = [
            'X-Correlation-ID' => (string) Context::get('correlation_id', ''),
        ];

        try {
            Globals::propagator()->inject($headers, null, OtelContext::getCurrent());
        } catch (\Throwable) {
            // OTel not initialized — fail silently, message still flows
        }

        return $headers;
    }
}
