<?php

namespace App\Repositories;

use App\Models\Task\TaskDefinitionAgent;
use Newms87\Danx\Repositories\ActionRepository;

class TaskDefinitionAgentRepository extends ActionRepository
{
    public static string $model = TaskDefinitionAgent::class;

    /**
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'copy' => $this->copyAgent($model, $data),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Copy an agent in a task definition
     */
    public function copyAgent(TaskDefinitionAgent $taskDefinitionAgent, ?array $input = []): TaskDefinitionAgent
    {
        $replicateAgent = $taskDefinitionAgent->replicate();
        $replicateAgent->fill($input)->save();

        foreach($taskDefinitionAgent->schemaAssociations as $schemaAssociation) {
            $replicateAgent->inputSchemaAssociations()->create([
                'schema_definition_id' => $schemaAssociation->schema_definition_id,
                'schema_fragment_id'   => $schemaAssociation->schema_fragment_id,
                'category'             => $schemaAssociation->category,
            ]);
        }

        return $replicateAgent;
    }
}
