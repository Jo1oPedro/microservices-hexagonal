<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Persistence\Model;

use App\Infrastructure\Model;
use Hyperf\Database\Model\Concerns\HasUuids;

/**
 * @property string $id 
 * @property string $name 
 * @property string $email 
 * @property string $password 
 * @property \Carbon\Carbon $created_at 
 * @property \Carbon\Carbon $updated_at 
 */
class User extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        "name",
        "email",
        "password",
    ];

    protected array $hidden = [
        "password",
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['created_at' => 'datetime', 'updated_at' => 'datetime'];
}
