<?php

declare(strict_types=1);

namespace App\Application\User\UseCase;

use App\Application\User\DTO\CreateUserInput;
use App\Application\User\DTO\CreateUserOutput;
use App\Application\User\Port\UserNotificationPort;
use App\Domain\User\Exception\UserEmailAlreadyExistsException;
use App\Domain\User\User as DomainUser;
use App\Domain\User\UserRepository;
use App\Infrastructure\Observability\Tracing\Annotation\Traced;

class CreateUser
{
    public function __construct(
        private UserRepository $userRepository,
        private UserNotificationPort $asyncQueueNotification,
    ) {}

    #[Traced(name: 'usecase.user.create')]
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
