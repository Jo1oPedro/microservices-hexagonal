<?php

use HyperfTest\DatabaseTestCase;

class CreateUserTest extends DatabaseTestCase
{
    protected array $tablesToClean = ["users"];

    public function test_creates_user_successfully(): void
    {
        $response = $this->post("/api/v1/users", [
           "name" => "joao",
           "email" => "joao@gmail.com",
           "password" => "12345678"
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath("user.email", "joao@gmail.com");
        $response->assertJsonStructure([
            "user" => ["id", "name", "email", "createdAt", "updatedAt"]
        ]);
    }

    public function test_rejects_duplicate_email(): void
    {
        $this->post("/api/v1/users", [
           "name" => "joao",
           "email" => "joao@gmail.com",
           "password" => "12345678"
        ]);

        $response = $this->post("/api/v1/users", [
            "name" => "cascata",
            "email" => "joao@gmail.com",
            "password" => "12345678"
        ]);

        $response->assertStatus(409);
    }

    public function test_reject_missing_fields(): void
    {
        $response = $this->post("/api/v1/users", []);

        $response->assertStatus(422);
    }
}