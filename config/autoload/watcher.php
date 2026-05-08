<?php

declare(strict_types=1);

return [
    'driver' => Hyperf\Watcher\Driver\ScanFileDriver::class,
    'bin' => 'php',
    'watch' => [
        'dir' => ['app', 'config'],
        'file' => ['.env'],
        'scan_interval' => 2000,
    ],
];
