<?php

namespace App\Resources\Prompt;

use App\Models\Prompt\PromptSchema;
use App\Models\Prompt\SchemaAssociation;
use Newms87\Danx\Resources\ActionResource;

class SchemaAssociationResource extends ActionResource
{
    public static function data(SchemaAssociation $schemaAssociation): array
    {
        return [
            'id'         => $schemaAssociation->id,
            'schema'     => PromptSchema::make($schemaAssociation->promptSchema),
            'fragment'   => PromptSchemaFragmentResource::make($schemaAssociation->promptSchemaFragment),
            'created_at' => $schemaAssociation->created_at,
            'updated_at' => $schemaAssociation->updated_at,
        ];
    }
}
