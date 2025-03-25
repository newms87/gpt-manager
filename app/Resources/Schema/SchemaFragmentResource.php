<?php

namespace App\Resources\Schema;

use App\Models\Schema\SchemaFragment;
use Newms87\Danx\Resources\ActionResource;

class SchemaFragmentResource extends ActionResource
{
    public static function data(SchemaFragment $schemaFragment): array
    {
        return [
            'id'                   => $schemaFragment->id,
            'schema_definition_id' => $schemaFragment->schema_definition_id,
            'name'                 => $schemaFragment->name,
            'fragment_selector'    => $schemaFragment->fragment_selector,
            'created_at'           => $schemaFragment->created_at,
            'updated_at'           => $schemaFragment->updated_at,
        ];
    }
}
