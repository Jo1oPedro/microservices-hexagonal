<?php

namespace App\Infrastructure\User\Notification;

use App\Application\User\Port\UserNotificationPort;
use App\Infrastructure\Observability\Tracing\Annotation\Traced;
use App\Infrastructure\User\Notification\Job\SendWelcomeEmail;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;

class AsyncQueueUserNotification implements UserNotificationPort
{
    private DriverInterface $queue;

    public function __construct(DriverFactory $driverFactory)
    {
        $this->queue = $driverFactory->get("default");
    }

    #[Traced(name: 'notification.welcome_email.enqueue', kind: 'producer')]
    public function sendWelcomeEmail(string $email): void
    {
        $this->queue->push(new SendWelcomeEmail($email));
    }
}