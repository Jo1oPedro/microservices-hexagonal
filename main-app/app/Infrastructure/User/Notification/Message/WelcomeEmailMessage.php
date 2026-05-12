<?php

namespace app\Infrastructure\User\Notification\Message;

use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Context\Context;
use Hyperf\Tracer\TracerContext;
use PhpAmqpLib\Wire\AMQPTable;

use const OpenTracing\Formats\TEXT_MAP;

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
        return json_encode(
            ["email" => $this->payload]
        );
    }

    private function buildTracingHeaders(): array
    {
        $headers = [
            'X-Correlation-ID' => (string) Context::get('correlation_id', ''),
        ];

        try {
            $tracer  = TracerContext::getTracer();
            $rootSpan = Context::get('tracer.root_span');

            if ($rootSpan !== null) {
                $tracer->inject($rootSpan->getContext(), TEXT_MAP, $headers);
            }
        } catch (\Throwable) {
        }

        return $headers;
    }
}
