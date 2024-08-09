<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObjectAttribute;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

abstract class TeamObjectAttributeResource extends ActionResource
{
    /**
     * @param TeamObjectAttribute $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'         => $model->id,
            'name'       => $model->name,
            'date'       => $model->date,
            'value'      => $model->json_value ?? $model->text_value,
            'source'     => StoredFileResource::make($model->sourceFile),
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
    }
}
