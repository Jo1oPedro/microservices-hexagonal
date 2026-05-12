<?php

declare(strict_types=1);

namespace App\Tracing;

final class Span
{
    public int $startMicros;
    public array $tags = [];

    public function __construct(
        private ZipkinTracer $tracer,
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $name,
        public bool $sampled,
    ) {
        $this->startMicros = (int) (microtime(true) * 1_000_000);
    }

    public function tag(string $key, string|int|bool|float $value): void
    {
        $this->tags[$key] = (string) $value;
    }

    public function finish(): void
    {
        $this->tracer->report($this);
    }
}
