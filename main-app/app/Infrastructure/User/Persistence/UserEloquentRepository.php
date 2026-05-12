<?php

namespace App\Infrastructure\User\Persistence;

use App\Domain\User\User as DomainUser;
use App\Domain\User\UserRepository;
use App\Infrastructure\User\Persistence\Model\User as EloquentUser;
use App\Tracing\Annotation\TraceLayer;

class UserEloquentRepository implements UserRepository
{
    #[TraceLayer(name: "repository.user.save", tag: "layer.repository")]
    public function save(DomainUser $user): DomainUser
    {
        $model = new EloquentUser();
        $model->id = $user->id;
        $model->email = $user->email;
        $model->name = $user->name;
        $model->password = $user->password;
        $model->save();
        $model->refresh();

        return $this->toDomain($model);
    }

    public function delete(string $id): void
    {
        EloquentUser::destroy($id);
    }

    private function toDomain(EloquentUser $model): DomainUser
    {
        return new DomainUser(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            password: $model->password,
            createdAt: new \DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: new \DateTimeImmutable($model->updated_at->toDateTimeString()),
        );
    }

    public function findByEmail(string $email): ?DomainUser
    {
        $model = EloquentUser::where("email", $email)->first();

        return $model ? $this->toDomain($model) : null;
    }
}
