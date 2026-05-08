<?php

namespace App\DTO;

class UserDTO
{
    public function __construct(
        private string $name,
        private string $email,
        private string $password
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function toArray()
    {
        return [
            "name" => $this->name,
            "email" => $this->email,
            "password" => $this->password
        ];
    }
}