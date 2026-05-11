<?php

namespace HyperfTest\Unit\Domain\User;

use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_create_user_with_correct_attributes(): void
    {
        $user = User::create("João", "joao@gmail.com", "hashed_password");

        $this->assertNotEmpty($user->id);
        $this->assertEquals("João", $user->name);
        $this->assertEquals("joao@gmail.com", $user->email);
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->createdAt);
    }

    public function test_generates_unique_ids(): void
    {
        $a = User::create("a", "a@gmail.com", "hash");
        $b = User::create("b", "b@gmail.com", "hash");

        $this->assertNotEquals($a->id, $b->id);
    }
}