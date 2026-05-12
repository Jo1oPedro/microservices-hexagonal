<?php

declare(strict_types=1);

namespace App\Adapter\Http\Controller\User;

use app\Adapter\Http\Request\User\CreateUserRequest;
use app\Application\User\DTO\CreateUserInput;
use app\Application\User\UseCase\CreateUser;
use App\Tracing\Annotation\TraceLayer;
use Hyperf\HttpServer\Contract\ResponseInterface;

class CreateUserController
{
    public function __construct(
      private CreateUser $userService
    ) {}

    #[TraceLayer(name: "http.controller.create.user", tag: "layer.controller")]
    public function create(CreateUserRequest $request, ResponseInterface $response)
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
