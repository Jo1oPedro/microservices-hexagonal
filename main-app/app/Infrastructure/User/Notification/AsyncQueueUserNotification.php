<?php

namespace App\Infrastructure\User\Notification;

use app\Application\User\Port\UserNotificationPort;
use app\Infrastructure\User\Notification\Job\SendWelcomeEmail;
use App\Tracing\Annotation\TraceLayer;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;

class AsyncQueueUserNotification implements UserNotificationPort
{
    private DriverInterface $queue;

    public function __construct(DriverFactory $driverFactory)
    {
        $this->queue = $driverFactory->get("default");
    }

    #[TraceLayer(name: "queue_notification.user.welcome", tag: "layer.notification_queue")]
    public function sendWelcomeEmail(string $email): void
    {
        $this->queue->push(new SendWelcomeEmail($email));
    }
}