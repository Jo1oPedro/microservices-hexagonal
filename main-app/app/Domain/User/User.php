<?php

declare(strict_types=1);

namespace App\Domain\User;

use Ramsey\Uuid\Uuid;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $password,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt
    ) {}

    public static function create(string $name, string $email, string $password): self
    {
        return new self(
            id: (string) Uuid::uuid4(),
            name: $name,
            email: $email,
            password: $password,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }
}