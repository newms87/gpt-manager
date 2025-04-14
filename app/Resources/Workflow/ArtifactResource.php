<?php

namespace App\Resources\Workflow;

use App\Models\Task\Artifact;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class ArtifactResource extends ActionResource
{
    public static function data(Artifact $artifact): array
    {
        return [
            'id'                    => $artifact->id,
            'name'                  => $artifact->name,
            'position'              => $artifact->position,
            'model'                 => $artifact->model,
            'created_at'            => $artifact->created_at,
            'child_artifacts_count' => $artifact->child_artifacts_count,
            'text_content'          => fn() => $artifact->text_content,
            'json_content'          => fn() => $artifact->canView() ? $artifact->json_content : [
                'message'          => 'You are not allowed to view this schema',
                'schemaDefinition' => $artifact->schemaDefinition->only(['id', 'type', 'name']),
            ],
            'files'                 => fn($fields) => StoredFileResource::collection($artifact->storedFiles->load('transcodes'), $fields),
            'meta'                  => fn($fields) => $artifact->meta,
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
