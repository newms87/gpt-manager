<?php

namespace App\Resources\TeamObject;

use App\Models\TeamObject\TeamObjectAttributeSource;
use App\Resources\Agent\MessageResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

abstract class TeamObjectAttributeSourceResource extends ActionResource
{
    /**
     * @param TeamObjectAttributeSource $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'            => $model->id,
            'source_type'   => $model->source_type,
            'source_id'     => $model->source_id,
            'location'      => $model->location,
            'explanation'   => $model->explanation,
            'sourceFile'    => StoredFileResource::make($model->sourceFile),
            'sourceMessage' => $model->sourceMessage ? MessageResource::details($model->sourceMessage) : null,
            'created_at'    => $model->created_at,
        ];
    }
}
