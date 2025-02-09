<?php

namespace App\Resources\Schema;

use App\Models\Schema\SchemaHistory;
use Newms87\Danx\Resources\ActionResource;

class SchemaDefinitionRevisionResource extends ActionResource
{
    public static function data(SchemaHistory $schemaHistory): array
    {
        return [
            'id'         => $schemaHistory->id,
            'schema'     => $schemaHistory->schema,
            'user_email' => $schemaHistory->user?->email,
            'created_at' => $schemaHistory->created_at,
        ];
    }
}
