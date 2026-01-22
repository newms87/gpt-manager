<?php

namespace App\Resources\Schema;

use App\Models\Schema\ArtifactCategoryDefinition;
use Newms87\Danx\Resources\ActionResource;

class ArtifactCategoryDefinitionResource extends ActionResource
{
    public static function data(ArtifactCategoryDefinition $artifactCategoryDefinition): array
    {
        return [
            'id'                   => $artifactCategoryDefinition->id,
            'schema_definition_id' => $artifactCategoryDefinition->schema_definition_id,
            'name'                 => $artifactCategoryDefinition->name,
            'label'                => $artifactCategoryDefinition->label,
            'prompt'               => $artifactCategoryDefinition->prompt,
            'fragment_selector'    => $artifactCategoryDefinition->fragment_selector,
            'editable'             => $artifactCategoryDefinition->editable,
            'deletable'            => $artifactCategoryDefinition->deletable,
            'created_at'           => $artifactCategoryDefinition->created_at,
            'updated_at'           => $artifactCategoryDefinition->updated_at,
        ];
    }
}
