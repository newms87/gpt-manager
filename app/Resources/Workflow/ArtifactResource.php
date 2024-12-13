<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Artifact;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class ArtifactResource extends ActionResource
{
    /**
     * @param Artifact $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'         => $model->id,
            'name'       => $model->name,
            'group'      => $model->group,
            'model'      => $model->model,
            'created_at' => $model->created_at,
        ];
    }

    /**
     * @param Artifact $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'content' => $model->content,
            'data'    => $model->data,
        ]);
    }

    public static function files(Artifact $artifact): array
    {
        $artifact->load('storedFiles.transcodes');

        return [
            'files' => StoredFileResource::collection($artifact->storedFiles, fn(StoredFile $file) => [
                'transcodes' => StoredFileResource::collection($file->transcodes),
            ]),
        ];
    }
}
