<?php

namespace App\Resources\Schema;

use App\Models\Schema\SchemaAssociation;
use Newms87\Danx\Resources\ActionResource;

class SchemaAssociationResource extends ActionResource
{
    public static function data(SchemaAssociation $schemaAssociation): array
    {
        return [
            'id'         => $schemaAssociation->id,
            'schema'     => SchemaDefinitionResource::make($schemaAssociation->schemaDefinition),
            'fragment'   => SchemaFragmentResource::make($schemaAssociation->schemaFragment),
            'created_at' => $schemaAssociation->created_at,
            'updated_at' => $schemaAssociation->updated_at,
        ];
    }
}
