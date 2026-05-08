<?php

namespace App\Services;

use App\DTO\UserDTO;
use App\Model\User;
use App\Domain\User\UserRepository;

class UserService
{
    public function __construct(
         private UserRepository $userRepository
    ) {}

    public function create(UserDTO $userDTO)
    {
        $user = new User($userDTO->toArray());
        //$emailWelcome = new WelcomeEmail();
        //$emailWelcome->sendEmail($user->email);
        return $this->userRepository->save($user);
    }
}