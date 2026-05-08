<?php

namespace App\Infrastructure;

use App\Domain\User\UserRepository;
use App\Model\User;

class UserEloquentRepository implements UserRepository
{

    public function save(User $user)
    {
        $user->save();
        return $user->refresh();
    }

    public function delete(int $id)
    {
        // TODO: Implement delete() method.
    }
}