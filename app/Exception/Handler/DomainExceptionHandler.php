<?php

namespace App\Exception\Handler;

use App\Domain\User\Exception\UserEmailAlreadyExistsException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Swow\Psr7\Message\ResponsePlusInterface;
use Throwable;

class DomainExceptionHandler extends ExceptionHandler
{
    private const STATUS_MAP = [
        UserEmailAlreadyExistsException::class => 409
    ];

    public function handle(Throwable $throwable, ResponsePlusInterface $response)
    {
        $this->stopPropagation();

        $status = self::STATUS_MAP[$throwable::class] ?? 422;

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status)
            ->withBody(
                new SwooleStream(json_encode([
                    "message" => $throwable->getMessage(),
                ]))
            );
    }

    public function isValid(Throwable $throwable): bool
    {
        return isset(self::STATUS_MAP[$throwable::class]);
    }
}