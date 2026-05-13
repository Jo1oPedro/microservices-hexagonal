<?php

declare(strict_types=1);

namespace App\Listener;

use App\Infrastructure\Observability\OpenTelemetryBootstrap;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\Framework\Event\BootApplication;

#[Listener(priority: 9999)]
final class InitializeOpenTelemetryListener implements ListenerInterface
{
    public function __construct(private readonly OpenTelemetryBootstrap $bootstrap) {}

    public function listen(): array
    {
        return [
            BootApplication::class,
            BeforeWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        $this->bootstrap->register();
    }
}
