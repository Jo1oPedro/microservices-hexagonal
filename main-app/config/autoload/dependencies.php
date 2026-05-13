<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use App\Application\User\Port\UserNotificationPort;
use App\Domain\User\UserRepository;
use App\Infrastructure\User\Notification\AsyncMQUserNotification;
use App\Infrastructure\User\Notification\AsyncQueueUserNotification;
use App\Infrastructure\User\Persistence\UserEloquentRepository;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use function Hyperf\Support\env;

return [
    UserRepository::class => UserEloquentRepository::class,
    MailerInterface::class => function () {
        $dsn = ApplicationContext::getContainer()
            ->get(ConfigInterface::class)
            ->get("mail.dsn");
        return new Mailer(Transport::fromDsn($dsn));
    },
    UserNotificationPort::class => function () {
        $container = ApplicationContext::getContainer();

        $class = match(env("NOTIFICATION_TYPE", "queue")) {
            "mq" => AsyncMQUserNotification::class,
            "queue" => AsyncQueueUserNotification::class,
            default => throw new InvalidArgumentException(
                "Invalid NOTIFICATION_TYPE value. Expected 'mq' or 'queue'."
            )
        };

        return $container->get($class);
    }
];
