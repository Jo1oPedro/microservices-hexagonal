<?php

namespace app\Infrastructure\User\Notification\Message;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\ProducerMessage;

#[Producer(exchange: "notifcations", routingKey: "user.welcome_email")]
class WelcomeEmailMessage extends ProducerMessage
{
    public function __construct(string $email)
    {
        $this->payload = ["email" => $email];
    }
}