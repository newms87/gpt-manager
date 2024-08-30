<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptSchema;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class PromptSchemaResource extends ActionResource
{
    /**
     * @param PromptSchema $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                  => $model->id,
            'name'                => $model->name,
            'description'         => $model->description,
            'schema_format'       => $model->schema_format,
            'schema'              => $model->schema,
            'response_example'    => $model->response_example,
            'agents_count'        => $model->agents_count,
            'workflow_jobs_count' => $model->workflow_jobs_count,
            'created_at'          => $model->created_at,
            'updated_at'          => $model->updated_at,
        ];
    }
}
