<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\User\User as DomainUser;
use App\Domain\User\UserRepository;
use App\DTO\CreateUserInput;
use App\DTO\CreateUserOutput;

class UserService
{
    public function __construct(
         private UserRepository $userRepository
    ) {}

    public function create(CreateUserInput $createUserInput): CreateUserOutput
    {
        $passwordHash = password_hash($createUserInput->password, PASSWORD_BCRYPT);

        $user = DomainUser::create($createUserInput->name, $createUserInput->email, $passwordHash);
        $user = $this->userRepository->save($user);

        //$emailWelcome = new WelcomeEmail();
        //$emailWelcome->sendEmail($user->email);
        return new CreateUserOutput(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: $user->updatedAt->format(\DateTimeInterface::ATOM),
        );
    }
}