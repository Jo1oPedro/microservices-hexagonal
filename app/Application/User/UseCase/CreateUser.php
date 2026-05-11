<?php

declare(strict_types=1);

namespace app\Application\User\UseCase;

use app\Application\User\DTO\CreateUserInput;
use app\Application\User\DTO\CreateUserOutput;
use app\Application\User\Port\UserNotificationPort;
use App\Domain\User\Exception\UserEmailAlreadyExistsException;
use App\Domain\User\User as DomainUser;
use App\Domain\User\UserRepository;

class CreateUser
{
    public function __construct(
        private UserRepository $userRepository,
        private UserNotificationPort $asyncQueueNotification,
    ) {}

    public function create(CreateUserInput $createUserInput): CreateUserOutput
    {
        if($this->userRepository->findByEmail($createUserInput->email)) {
            throw new UserEmailAlreadyExistsException($createUserInput->email);
        }

        $passwordHash = password_hash($createUserInput->password, PASSWORD_DEFAULT);

        $user = DomainUser::create($createUserInput->name, $createUserInput->email, $passwordHash);
        $user = $this->userRepository->save($user);

        $this->asyncQueueNotification->sendWelcomeEmail($user->email);

        return new CreateUserOutput(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: $user->updatedAt->format(\DateTimeInterface::ATOM),
        );
    }
}