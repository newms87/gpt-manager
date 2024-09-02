<?php

namespace App\Resources\Auth;

use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TeamResource extends ActionResource
{
    /**
     * @param Team $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'        => $model->id,
            'name'      => $model->name,
            'namespace' => $model->namespace,
            'logo'      => $model->logo,
        ];
    }
}
