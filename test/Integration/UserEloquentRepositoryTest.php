<?php

namespace HyperfTest\Integration\Infrastructure;

use App\Domain\User\User;
use App\Infrastructure\User\Persistence\UserEloquentRepository;
use HyperfTest\DatabaseTestCase;

class UserEloquentRepositoryTest extends DatabaseTestCase
{
    protected array $tablesToClean = ["users"];
    private UserEloquentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserEloquentRepository();
    }

    public function test_persists_and_returns_domain_user(): void
    {
        $user = User::create(
            "joao",
            "joao@gmail.com",
            password_hash("123", PASSWORD_BCRYPT)
        );

        $saved = $this->repository->save($user);

        $this->assertEquals($user->id, $saved->id);
        $this->assertEquals("joao", $saved->name);
        $this->assertInstanceOf(\DateTimeImmutable::class, $saved->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $saved->updatedAt);
    }
}