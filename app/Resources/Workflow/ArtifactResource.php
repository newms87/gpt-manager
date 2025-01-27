<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Artifact;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class ArtifactResource extends ActionResource
{
    public static function data(Artifact $artifact): array
    {
        return [
            'id'           => $artifact->id,
            'name'         => $artifact->name,
            'group'        => $artifact->group,
            'model'        => $artifact->model,
            'created_at'   => $artifact->created_at,
            'text_content' => fn() => $artifact->text_content,
            'json_content' => fn() => $artifact->json_content,
            'files'        => fn($fields) => StoredFileResource::collection($artifact->storedFiles->load('transcodes'), $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*'     => true,
            'files' => false,
        ]);
    }
}
