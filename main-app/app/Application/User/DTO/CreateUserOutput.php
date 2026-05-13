<?php

namespace App\Application\User\DTO;

final readonly class CreateUserOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $createdAt,
        public string $updatedAt
    ) {}

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt
        ];
    }
}