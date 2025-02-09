<?php

namespace App\Resources\Schema;

use App\Models\Schema\SchemaDefinition;
use Newms87\Danx\Resources\ActionResource;

class SchemaDefinitionResource extends ActionResource
{
    public static function data(SchemaDefinition $schemaDefinition): array
    {
        return [
            'id'                  => $schemaDefinition->id,
            'name'                => $schemaDefinition->name,
            'description'         => $schemaDefinition->description,
            'schema_format'       => $schemaDefinition->schema_format,
            'schema'              => $schemaDefinition->schema,
            'response_example'    => $schemaDefinition->response_example,
            'agents_count'        => $schemaDefinition->agents_count,
            'workflow_jobs_count' => $schemaDefinition->workflow_jobs_count,
            'created_at'          => $schemaDefinition->created_at,
            'updated_at'          => $schemaDefinition->updated_at,

            'fragments' => fn($fields) => SchemaFragmentResource::collection($schemaDefinition->fragments, $fields),
        ];
    }
}
