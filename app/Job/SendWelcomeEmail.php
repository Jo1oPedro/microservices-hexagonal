<?php

declare(strict_types=1);

namespace App\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SendWelcomeEmail extends Job
{
    public function __construct(
        private readonly string $email,
    ) {}

    public function handle()
    {
        $container = ApplicationContext::getContainer();
        $mailer = $container->get(MailerInterface::class);
        $config = $container->get(ConfigInterface::class);

        $message = new Email()
            ->from($config->get("mail.from"))
            ->to($this->email)
            ->subject("Welcome to {$config->get("app_name")}")
            ->html("<p>Hello {$config->get("app_name")}</p>");

        $mailer->send($message);
    }
}
