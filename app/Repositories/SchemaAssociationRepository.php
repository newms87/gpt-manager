<?php

namespace App\Repositories;

use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nette\Schema\ValidationException;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class SchemaAssociationRepository extends ActionRepository
{
    public static string $model = SchemaAssociation::class;

    public function query(): Builder
    {
        return parent::query()->whereHas('schemaDefinition', fn(Builder $builder) => $builder->where('team_id', team()->id));
    }

    public function applyAction(string $action, SchemaAssociation|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createAssociation($data),
            'update' => $this->updateAssociation($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create a new Schema Association.
     * Only allows creating an association to predetermined object types so they can be validated
     */
    public function createAssociation($input): SchemaAssociation
    {
        $taskDefinitionId      = $input['task_definition_id'] ?? null;
        $taskDefinitionAgentId = $input['task_definition_agent_id'] ?? null;

        if ($taskDefinitionId) {
            $objectType = TaskDefinition::class;
            $objectId   = $taskDefinitionId;

            if (TaskDefinition::where('team_id', team()->id)->where('id', $taskDefinitionId)->doesntExist()) {
                throw new ValidationError('Task Definition was not found: ' . $taskDefinitionId);
            }
        } elseif ($taskDefinitionAgentId) {
            $objectType = TaskDefinitionAgent::class;
            $objectId   = $taskDefinitionAgentId;

            if (TaskDefinitionAgent::whereHas('taskDefinition', fn($b) => $b->where('team_id', team()->id))->where('id', $taskDefinitionAgentId)->doesntExist()) {
                throw new ValidationError('Task Definition Agent was not found: ' . $taskDefinitionAgentId);
            }
        } else {
            throw new ValidationException('An object type to associate the schema to was not specified.');
        }

        $association = SchemaAssociation::make()->forceFill([
            'object_type' => $objectType,
            'object_id'   => $objectId,
        ]);

        if (!isset($input['schema_definition_id'])) {
            $input['schema_definition_id'] = SchemaDefinition::where('team_id', team()->id)->first()?->id;
        }

        // Update will validate the schema definition and fragment
        return $this->updateAssociation($association, $input);
    }

    /**
     * Update a Schema Association verifying the schema and fragment exist
     */
    public function updateAssociation(SchemaAssociation $schemaAssociation, array $input): SchemaAssociation
    {
        $schemaAssociation->fill($input);

        if ($schemaAssociation->schemaDefinition()->doesntExist()) {
            throw new ValidationError('Schema Definition was not found: ' . $schemaAssociation->schema_definition_id);
        }

        // Verify this schema fragment exists and belongs to the users team
        if ($schemaAssociation->schema_fragment_id) {
            $schemaFragment = SchemaFragment::whereHas('schemaDefinition', fn($b) => $b->where('team_id', team()->id))->find($schemaAssociation->schema_fragment_id);

            if (!$schemaFragment) {
                throw new ValidationError('Schema Fragment was not found: ' . $schemaAssociation->schema_fragment_id);
            }
        }

        $schemaAssociation->save();

        return $schemaAssociation;
    }
}
