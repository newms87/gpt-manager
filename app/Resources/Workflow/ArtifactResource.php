<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Artifact;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class ArtifactResource extends ActionResource
{
    /**
     * @param Artifact $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return static::make($model, [
                'id'         => $model->id,
                'name'       => $model->name,
                'group'      => $model->group,
                'model'      => $model->model,
                'created_at' => $model->created_at,
            ] + $attributes);
    }

    /**
     * @param Artifact $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'content' => $model->content,
            'data'    => $model->data,
        ]);
    }
}
