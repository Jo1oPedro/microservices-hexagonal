<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Domain\User\Exception\UserEmailAlreadyExistsException;
use App\Domain\User\User as DomainUser;
use App\Domain\User\UserRepository;
use App\DTO\CreateUserInput;
use App\DTO\CreateUserOutput;
use App\Job\SendWelcomeEmail;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;

class CreateUser
{
    private readonly DriverInterface $queue;
    public function __construct(
        private UserRepository $userRepository,
        private DriverFactory $driverFactory
    ) {
        $this->queue = $this->driverFactory->get("default");
    }

    public function create(CreateUserInput $createUserInput): CreateUserOutput
    {
        if($this->userRepository->findByEmail($createUserInput->email)) {
            throw new UserEmailAlreadyExistsException($createUserInput->email);
        }

        $passwordHash = password_hash($createUserInput->password, PASSWORD_BCRYPT);

        $user = DomainUser::create($createUserInput->name, $createUserInput->email, $passwordHash);
        $user = $this->userRepository->save($user);

        $this->queue->push(new SendWelcomeEmail($user->email));

        return new CreateUserOutput(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: $user->updatedAt->format(\DateTimeInterface::ATOM),
        );
    }
}