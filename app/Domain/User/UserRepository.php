<?php

namespace App\Domain\User;

use App\Model\User;

interface UserRepository
{
    public function save(User $user);

    public function delete(int $id);
}