<?php

namespace app\Application\User\Port;

interface UserNotificationPort
{
    public function sendWelcomeEmail(string $email): void;
}