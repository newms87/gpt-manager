<?php

namespace App\Resources\Schema;

use App\Models\Schema\SchemaDefinition;
use Newms87\Danx\Resources\ActionResource;

class SchemaDefinitionResource extends ActionResource
{
    public static function data(SchemaDefinition $schemaDefinition): array
    {
        $isOwner = $schemaDefinition->owner_team_id === null;

        return [
            'id'               => $schemaDefinition->id,
            'name'             => $schemaDefinition->name,
            'description'      => $schemaDefinition->description,
            'schema_format'    => $schemaDefinition->schema_format,
            'schema'           => $isOwner ? $schemaDefinition->schema : null,
            'response_example' => $schemaDefinition->response_example,
            'created_at'       => $schemaDefinition->created_at,
            'updated_at'       => $schemaDefinition->updated_at,

            'can' => [
                'view' => $isOwner,
                'edit' => $isOwner,
            ],

            'fragments' => fn($fields) => SchemaFragmentResource::collection($schemaDefinition->fragments, $fields),
        ];
    }
}
