<?php

namespace App\Infrastructure\User\Notification;

use App\Application\User\Port\UserNotificationPort;
use App\Infrastructure\Observability\Tracing\Annotation\Traced;
use App\Infrastructure\User\Notification\Message\WelcomeEmailMessage;
use Hyperf\Amqp\Producer;

final readonly class AsyncMQUserNotification implements UserNotificationPort
{
    public function __construct(
        private Producer $producer
    ) {}

    #[Traced(name: 'notification.welcome_email.publish', kind: 'producer')]
    public function sendWelcomeEmail(string $email): void
    {
        $this->producer->produce(new WelcomeEmailMessage($email));
    }
}
