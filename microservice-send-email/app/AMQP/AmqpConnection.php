<?php

namespace App\AMQP;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;

class AmqpConnection
{
    private static ?AbstractConnection $instance = null;
    private function __construct() {}

    public static function getInstance(): AbstractConnection
    {
        if (self::$instance === null) {
            $amqpConfig = new AMQPConnectionConfig();
            $amqpConfig->setHost($_ENV['AMQP_HOST']);
            $amqpConfig->setPort($_ENV['AMQP_PORT']);
            $amqpConfig->setUser($_ENV['AMQP_USER']);
            $amqpConfig->setPassword($_ENV['AMQP_PASSWORD']);

            if($_ENV['APP_ENV'] === "production") {
                $amqpConfig->setIsSecure(true);
            }

            self::$instance = AMQPConnectionFactory::create($amqpConfig);
        }

        return self::$instance;
    }
}