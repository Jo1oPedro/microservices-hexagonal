<?php

use function Hyperf\Support\env;

return [
    'dsn'  => env('MAIL_DSN', 'null://null'),
    'from' => env('MAIL_FROM', 'no-reply@example.com'),
];