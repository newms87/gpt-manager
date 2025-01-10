<?php

namespace App\Resources\Auth;

use App\Models\User;
use Newms87\Danx\Resources\ActionResource;

class UserResource extends ActionResource
{
    public static function data(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }
}
