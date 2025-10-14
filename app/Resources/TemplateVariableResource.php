<?php

namespace App\Resources;

use App\Models\Demand\TemplateVariable;
use App\Resources\Schema\SchemaAssociationResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TemplateVariableResource extends ActionResource
{
    public static function data(TemplateVariable $variable): array
    {
        return [
            'id'                                => $variable->id,
            'demand_template_id'                => $variable->demand_template_id,
            'name'                              => $variable->name,
            'description'                       => $variable->description,
            'mapping_type'                      => $variable->mapping_type,
            'artifact_categories'               => $variable->artifact_categories,
            'artifact_fragment_selector'        => $variable->artifact_fragment_selector,
            'team_object_schema_association_id' => $variable->team_object_schema_association_id,
            'ai_instructions'                   => $variable->ai_instructions,
            'multi_value_strategy'              => $variable->multi_value_strategy,
            'multi_value_separator'             => $variable->multi_value_separator,
            'created_at'                        => $variable->created_at,
            'updated_at'                        => $variable->updated_at,

            // Relationships (loaded conditionally)
            'schema_association' => fn($fields) => $variable->teamObjectSchemaAssociation
                ? SchemaAssociationResource::make($variable->teamObjectSchemaAssociation, $fields)
                : null,
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'schema_association' => true,
        ]);
    }
}
