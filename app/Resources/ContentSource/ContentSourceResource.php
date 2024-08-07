<?php

namespace App\Resources\ContentSource;

use App\Models\ContentSource\ContentSource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class ContentSourceResource extends ActionResource
{
    /**
     * @param ContentSource $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                    => $model->id,
            'name'                  => $model->name,
            'type'                  => $model->type,
            'url'                   => $model->url,
            'polling_interval'      => $model->polling_interval,
            'last_checkpoint'       => $model->last_checkpoint,
            'fetched_at'            => $model->fetched_at,
            'workflow_inputs_count' => $model->workflow_inputs_count,
            'created_at'            => $model->created_at,
        ];
    }

    /**
     * @param ContentSource $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'config' => $model->config,
        ]);
    }
}
