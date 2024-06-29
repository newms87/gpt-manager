<?php

namespace App\Resources\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class UserResource extends ActionResource
{
    /**
     * @param User $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'    => $model->id,
            'name'  => $model->name,
            'email' => $model->email,
        ];
    }
}
