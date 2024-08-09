<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

abstract class TeamObjectResource extends ActionResource
{
    /**
     * @param TeamObject $model
     */
    public static function data(Model $model): array
    {
        $attributes = $model->attributes()
            ->get()
            ->keyBy('name')
            ->map(fn(TeamObjectAttribute $attribute) => TeamObjectAttributeResource::make($attribute));

        return [
                'id'          => $model->id,
                'name'        => $model->name,
                'description' => $model->description,
                'url'         => $model->url,
                'meta'        => $model->meta,
                'created_at'  => $model->created_at,
                'updated_at'  => $model->updated_at,
            ] + $attributes->toArray();
    }
}
