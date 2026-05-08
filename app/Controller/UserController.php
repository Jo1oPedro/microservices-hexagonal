<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\UserDTO;
use App\Model\User;
use App\Request\CreateUserRequest;
use App\Services\UserService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

class UserController
{
    public function __construct(
      private UserService $userService
    ) {}

    public function index(RequestInterface $request, ResponseInterface $response)
    {
        return $response->raw('Hello Hyperf!');
    }

    public function store(CreateUserRequest $request, ResponseInterface $response)
    {
        $user = $this->userService->create(
            new UserDTO(...$request->validated())
        );

        return $response->json(["user12" => $user], 201);
    }
}
