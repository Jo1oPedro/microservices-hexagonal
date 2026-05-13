<?php

namespace App\Application\User\DTO;

final readonly class CreateUserInput
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {}

    public function toArray()
    {
        return [
            "name" => $this->name,
            "email" => $this->email,
            "password" => $this->password
        ];
    }
}