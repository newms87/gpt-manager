<?php

namespace App\Services\Demand;

use App\Models\Schema\SchemaAssociation;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateVariable;
use App\Repositories\SchemaAssociationRepository;
use App\Repositories\TemplateVariableRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;

class TemplateVariableService
{
    /**
     * Sync variables from Google Doc template
     * Creates new variables with default mapping_type='ai', preserves existing configurations, deletes orphaned variables
     */
    public function syncVariablesFromGoogleDoc(TemplateDefinition $template, array $variableNames): Collection
    {
        $this->validateTemplateOwnership($template);

        return DB::transaction(function () use ($template, $variableNames) {
            // Get existing variables for this template
            $existingVariables       = app(TemplateVariableRepository::class)->findForTemplate($template);
            $existingVariablesByName = $existingVariables->keyBy('name');

            // Track which variables we're keeping
            $keptVariableNames = [];

            // Create or update variables based on Google Doc
            foreach ($variableNames as $variableName) {
                if ($existingVariablesByName->has($variableName)) {
                    // Variable exists - keep its configuration
                    $keptVariableNames[] = $variableName;
                } else {
                    // New variable - create with default mapping_type='ai'
                    $newVariable = new TemplateVariable([
                        'template_definition_id' => $template->id,
                        'name'                   => $variableName,
                        'description'            => '',
                        'mapping_type'           => TemplateVariable::MAPPING_TYPE_AI,
                        'multi_value_strategy'   => TemplateVariable::STRATEGY_JOIN,
                        'multi_value_separator'  => ', ',
                    ]);
                    $newVariable->save();
                    $keptVariableNames[] = $variableName;
                }
            }

            // Delete variables that no longer exist in the template
            $variablesToDelete = $existingVariables->filter(function ($variable) use ($keptVariableNames) {
                return !in_array($variable->name, $keptVariableNames);
            });

            foreach ($variablesToDelete as $variable) {
                $variable->delete();
            }

            // Return updated list of variables
            return app(TemplateVariableRepository::class)->findForTemplate($template);
        });
    }

    /**
     * Update an existing template variable
     */
    public function updateVariable(TemplateVariable $variable, array $data): TemplateVariable
    {
        $this->validateTemplateOwnership($variable->templateDefinition);
        $this->validateVariableData($data, $variable);

        return DB::transaction(function () use ($variable, $data) {
            $variable->fill($data);
            $variable->save();

            // Update or create SchemaAssociation if TeamObject mapping type
            if ($variable->isTeamObjectMapped()) {
                if (!empty($data['schema_definition_id'])) {
                    $schemaFragmentId = $data['schema_fragment_id'] ?? null;
                    $this->createSchemaAssociationForVariable($variable, $data['schema_definition_id'], $schemaFragmentId);
                }
            } else {
                // Remove SchemaAssociation if no longer TeamObject type
                if ($variable->teamObjectSchemaAssociation) {
                    $variable->teamObjectSchemaAssociation->delete();
                    $variable->team_object_schema_association_id = null;
                    $variable->save();
                }
            }

            return $variable->fresh();
        });
    }

    /**
     * Create or update SchemaAssociation for a template variable
     */
    public function createSchemaAssociationForVariable(
        TemplateVariable $variable,
        int $schemaDefinitionId,
        ?int $schemaFragmentId = null
    ): SchemaAssociation {
        // Check if association already exists
        if ($variable->teamObjectSchemaAssociation) {
            $association = app(SchemaAssociationRepository::class)->updateAssociation(
                $variable->teamObjectSchemaAssociation,
                [
                    'schema_definition_id' => $schemaDefinitionId,
                    'schema_fragment_id'   => $schemaFragmentId,
                ]
            );
        } else {
            // Create new association
            $association = new SchemaAssociation([
                'schema_definition_id' => $schemaDefinitionId,
                'schema_fragment_id'   => $schemaFragmentId,
            ]);
            // Set polymorphic relationship fields directly (not fillable)
            $association->object_type = TemplateVariable::class;
            $association->object_id   = $variable->id;
            $association->save();

            // Update variable with association ID
            $variable->team_object_schema_association_id = $association->id;
            $variable->save();
        }

        return $association;
    }

    /**
     * Validate template ownership
     */
    protected function validateTemplateOwnership(TemplateDefinition $template): void
    {
        $currentTeam = team();
        if (!$currentTeam || $template->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this template definition', 403);
        }
    }

    /**
     * Validate variable data
     */
    protected function validateVariableData(array $data, ?TemplateVariable $variable = null): void
    {
        // Validate artifact_fragment_selector is an array when present
        if (isset($data['artifact_fragment_selector']) && !is_array($data['artifact_fragment_selector'])) {
            throw new ValidationError('artifact_fragment_selector must be a valid array', 422);
        }

        // TeamObject mapping: configuration fields (team_object_schema_association_id, schema_definition_id) are optional - user can save incomplete configuration
        // Artifact mapping: categories and fragment_selector are optional - user can select all artifacts
        // AI mapping does not require ai_instructions - it's optional
    }
}
