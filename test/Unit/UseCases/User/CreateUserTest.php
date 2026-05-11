<?php

namespace HyperfTest\Unit\UseCases\User;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\DTO\CreateUserInput;

use App\DTO\CreateUserOutput;
use App\Job\SendWelcomeEmail;
use App\UseCases\User\CreateUser;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;
use Hyperf\Testing\TestCase;
use Mockery;
use Mockery\MockInterface;

class CreateUserTest extends TestCase
{
    private MockInterface $repository;
    private MockInterface $queue;
    private CreateUser $createUser;

    protected function setUp(): void
    {
        $this->repository = Mockery::mock(UserRepository::class);
        $this->queue = Mockery::mock(DriverInterface::class);

        $driverFactory = Mockery::mock(DriverFactory::class);
        $driverFactory
            ->shouldReceive('get')
            ->with('default')
            ->andReturn($this->queue);

        $this->createUser = new CreateUser($this->repository, $driverFactory);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_saves_user_and_returns_output(): void
    {
        $domainUser = User::create("joao", "joao@gmail.com", "hashed");

        $this->repository
            ->shouldReceive("findByEmail")
            ->with($domainUser->email)
            ->andReturn(null);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->andReturn($domainUser);

        $this->queue
            ->shouldReceive("push")
            ->once()
            ->with(Mockery::type(SendWelcomeEmail::class));

        $output = $this->createUser->create(
            new CreateUserInput(
                name: $domainUser->name,
                email: $domainUser->email,
                password: $domainUser->password
            ),
        );

        $this->assertEquals("joao", $output->name);
        $this->assertEquals("joao@gmail.com", $output->email);
        $this->assertNotEmpty($output->id);
    }

    public function test_hashes_password_before_saving(): void
    {
        $this->repository
            ->shouldReceive("findByEmail")
            ->with("joao@gmail.com")
            ->andReturn(null);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (User $user) {
                return password_verify("senha123", $user->password);
            }))
            ->andReturn(User::create("joao", "joao@gmail.com", "senha123"));

        $this->queue
            ->shouldReceive("push")
            ->once()
            ->with(Mockery::type(SendWelcomeEmail::class));

        $user = $this->createUser->create(
            new CreateUserInput("joao", "joao@gmail.com", "senha123")
        );

        $this->assertInstanceOf(CreateUserOutput::class, $user);
    }
}