<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptSchema;
use Newms87\Danx\Resources\ActionResource;

class PromptSchemaResource extends ActionResource
{
    public static function data(PromptSchema $promptSchema): array
    {
        return [
            'id'                  => $promptSchema->id,
            'name'                => $promptSchema->name,
            'description'         => $promptSchema->description,
            'schema_format'       => $promptSchema->schema_format,
            'schema'              => $promptSchema->schema,
            'response_example'    => $promptSchema->response_example,
            'agents_count'        => $promptSchema->agents_count,
            'workflow_jobs_count' => $promptSchema->workflow_jobs_count,
            'created_at'          => $promptSchema->created_at,
            'updated_at'          => $promptSchema->updated_at,
        ];
    }
}
