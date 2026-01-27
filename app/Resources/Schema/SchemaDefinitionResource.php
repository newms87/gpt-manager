<?php

namespace App\Resources\Schema;

use App\Models\Schema\SchemaDefinition;
use Newms87\Danx\Resources\ActionResource;

class SchemaDefinitionResource extends ActionResource
{
    public static function data(SchemaDefinition $schemaDefinition): array
    {
        return [
            'id'               => $schemaDefinition->id,
            'name'             => $schemaDefinition->name,
            'description'      => $schemaDefinition->description,
            'schema_format'    => $schemaDefinition->schema_format,
            'schema'           => $schemaDefinition->canView() ? $schemaDefinition->schema : null,
            'response_example' => $schemaDefinition->response_example,
            'created_at'       => $schemaDefinition->created_at,
            'updated_at'       => $schemaDefinition->updated_at,

            'can' => [
                'view' => $schemaDefinition->canView(),
                'edit' => $schemaDefinition->canEdit(),
            ],

            'fragments'                     => fn($fields) => SchemaFragmentResource::collection($schemaDefinition->fragments, $fields),
            'artifact_category_definitions' => fn($fields) => ArtifactCategoryDefinitionResource::collection($schemaDefinition->artifactCategoryDefinitions, $fields),
        ];
    }
}
