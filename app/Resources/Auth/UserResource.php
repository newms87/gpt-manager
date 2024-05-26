<?php

namespace App\Resources\Auth;

use App\Models\User;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin User
 * @property User $resource
 */
class UserResource extends ActionResource
{
    protected static string $type = 'User';

    public function data(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
        ];
    }
}
