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

use App\Domain\User\UserRepository;
use App\Infrastructure\UserEloquentRepository;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

return [
    UserRepository::class => UserEloquentRepository::class,
    MailerInterface::class => function () {
        $dsn = ApplicationContext::getContainer()
            ->get(ConfigInterface::class)
            ->get("mail.dsn");
        return new Mailer(Transport::fromDsn($dsn));
    }
];
