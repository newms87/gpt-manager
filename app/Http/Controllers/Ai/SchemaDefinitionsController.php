<?php

namespace App\Http\Controllers\Ai;

use App\Models\Schema\SchemaDefinition;
use App\Repositories\SchemaDefinitionRepository;
use App\Resources\Schema\SchemaDefinitionResource;
use App\Resources\Schema\SchemaDefinitionRevisionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class SchemaDefinitionsController extends ActionController
{
    public static ?string $repo     = SchemaDefinitionRepository::class;

    public static ?string $resource = SchemaDefinitionResource::class;

    public function history(SchemaDefinition $schemaDefinition)
    {
        if ($schemaDefinition->canEdit()) {
            return SchemaDefinitionRevisionResource::collection($schemaDefinition->schemaDefinitionRevisions()->orderByDesc('id')->get());
        }

        return [];
    }
}
