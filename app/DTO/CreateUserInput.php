<?php

namespace App\DTO;

class CreateUserInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
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