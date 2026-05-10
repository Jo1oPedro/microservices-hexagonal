<?php

namespace App\Infrastructure;

use App\Domain\User\UserRepository;
use App\Domain\User\User as DomainUser;
use App\Model\User as EloquentUser;

class UserEloquentRepository implements UserRepository
{
    public function save(DomainUser $user): DomainUser
    {
        $model = new EloquentUser();
        $model->id = $user->id;
        $model->email = $user->email;
        $model->name = $user->name;
        $model->password = $user->passwordHash;
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
            passwordHash: $model->password,
            createdAt: new \DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: new \DateTimeImmutable($model->updated_at->toDateTimeString()),
        );
    }
}