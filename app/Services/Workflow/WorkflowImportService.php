<?php

namespace App\Services\Workflow;

use App\Models\Agent\Agent;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\Prompt\PromptDirective;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionAgent;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;

class WorkflowImportService
{
    protected int    $ownerTeamId;
    protected string $versionHash;
    protected string $versionDate;

    protected array $importedIdMap = [];

    public function importFromJson(array $workflowDefinitionJson): WorkflowDefinition
    {
        $this->ownerTeamId = $workflowDefinitionJson['owner_team_id'] ?? null;
        $this->versionHash = $workflowDefinitionJson['version_hash'] ?? null;
        $this->versionDate = $workflowDefinitionJson['version_date'] ?? null;
        $definitions       = $workflowDefinitionJson['definitions'] ?? [];

        $importClasses = [
            PromptDirective::class      => ['is_team' => true],
            Agent::class                => ['is_team' => true],
            SchemaDefinition::class     => ['is_team' => true],
            SchemaFragment::class       => [],
            TaskDefinition::class       => ['is_team' => true],
            TaskDefinitionAgent::class  => [],
            SchemaAssociation::class    => [],
            AgentPromptDirective::class => [],
            WorkflowDefinition::class   => ['is_team' => true],
            WorkflowNode::class         => [],
            WorkflowConnection::class   => [],
        ];

        foreach($importClasses as $importClass => $config) {
            $this->importClass($importClass, $config, $definitions[$importClass] ?? []);
        }

        $workflowDefinitions  = $this->importedIdMap[WorkflowDefinition::class] ?? [];
        $workflowDefinitionId = reset($workflowDefinitions);
        $workflowDefinition   = WorkflowDefinition::find($workflowDefinitionId);

        if (!$workflowDefinition) {
            throw new ValidationError('Failed to import workflow definition: The object was not created');
        }

        return $workflowDefinition;
    }

    /**
     * Import a class from the JSON data
     * @param Model|string $importClass
     **/
    protected function importClass(string $importClass, array $config, array $definitions): void
    {
        foreach($definitions as $exportedId => $definition) {
            $isTeam = !empty($config['is_team']);

            // Try to resolve the existing instance if it exists
            $query = $importClass::where('owner_object_id', $exportedId)
                ->where('owner_team_id', $this->ownerTeamId);

            if ($isTeam) {
                $query->where('team_id', team()->id);
            }
            $instance = $query->first();

            if (!$instance) {
                $instance = (new $importClass);

                if ($isTeam) {
                    $instance->team_id = team()->id;
                }

                $instance->owner_team_id   = $this->ownerTeamId;
                $instance->owner_object_id = $exportedId;
            }

            // Update the version hash / date
            $instance->version_hash = $this->versionHash;
            $instance->version_date = $this->versionDate;

            foreach($definition as $key => $value) {
                // First check if the value is a reference to another object
                if ($value && is_string($value)) {
                    $parts = explode(':', $value);
                    if (count($parts) === 2) {
                        $relationClass = $parts[0];
                        $relationId    = $parts[1];
                        $mappedId      = $this->importedIdMap[$relationClass][$relationId] ?? null;

                        // resolve the mapped ID instead of using the original value
                        if ($mappedId) {
                            $value = $mappedId;
                        }
                    }
                }

                if ($key === 'name') {
                    $value .= ' (' . substr($this->versionHash, 0, 8) . ')';
                }
                $instance->$key = $value;
            }

            $instance->save();
            $this->importedIdMap[$importClass][$exportedId] = $instance->id;
        }
    }
}
