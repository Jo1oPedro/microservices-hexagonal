<?php

namespace App\Infrastructure\User\Notification;

use app\Application\User\Port\UserNotificationPort;
use app\Infrastructure\User\Notification\Message\WelcomeEmailMessage;
use App\Tracing\Annotation\TraceLayer;
use Hyperf\Amqp\Producer;

final readonly class AsyncMQUserNotification implements UserNotificationPort
{
    public function __construct(
        private Producer $producer
    ) {}

    #[TraceLayer(name: "mq_notification.user.welcome", tag: "layer.notification_mq")]
    public function sendWelcomeEmail(string $email): void
    {
        $this->producer->produce(new WelcomeEmailMessage($email));
    }
}
