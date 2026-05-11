<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\CreateUserInput;
use App\Request\CreateUserRequest;
use App\UseCases\User\CreateUser;
use Hyperf\HttpServer\Contract\ResponseInterface;

class CreateUserController
{
    public function __construct(
      private CreateUser $userService
    ) {}

    public function __invoke(CreateUserRequest $request, ResponseInterface $response)
    {
        $fieldsValidated = $request->validated();
        $user = $this->userService->create(
            new CreateUserInput(
                name: $fieldsValidated['name'],
                email: $fieldsValidated['email'],
                password: $fieldsValidated['password']
            )
        );

        return $response->json(["user" => $user->toArray()])->withStatus(201);
    }
}
