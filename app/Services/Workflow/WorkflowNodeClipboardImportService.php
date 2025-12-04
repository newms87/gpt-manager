<?php

namespace App\Services\Workflow;

use App\Models\Agent\Agent;
use App\Models\Prompt\PromptDirective;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;

class WorkflowNodeClipboardImportService
{
    protected array $importedIdMap = [];  // Maps "ClassName:sourceId" => localId

    protected array $nodeIdMap = [];      // Maps export_key => new node ID

    protected array $positionOffset = ['x' => 0, 'y' => 0];

    /**
     * Import nodes from clipboard data into target workflow.
     *
     * @param  array  $pastePosition  ['x' => float, 'y' => float]
     * @return WorkflowNode[] Array of created nodes
     */
    public function importNodes(
        WorkflowDefinition $targetWorkflow,
        array $clipboardData,
        array $pastePosition
    ): array {
        if (($clipboardData['type'] ?? null) !== 'workflow-node-clipboard') {
            throw new ValidationError('Invalid clipboard data: not a workflow node clipboard');
        }

        $nodes       = $clipboardData['nodes']       ?? [];
        $connections = $clipboardData['connections'] ?? [];
        $definitions = $clipboardData['definitions'] ?? [];

        if (empty($nodes)) {
            throw new ValidationError('No nodes to import');
        }

        $this->calculatePositionOffset($nodes, $pastePosition);

        return DB::transaction(function () use ($targetWorkflow, $nodes, $connections, $definitions) {
            // Import in dependency order
            $importClasses = [
                PromptDirective::class         => ['is_team' => true, 'match_fields' => ['name', 'directive_text']],
                Agent::class                   => ['is_team' => true, 'match_fields' => ['name', 'model', 'description', 'api_options']],
                SchemaDefinition::class        => ['is_team' => true, 'match_fields' => ['name', 'type', 'schema_format', 'schema']],
                SchemaFragment::class          => ['match_fields' => ['name', 'fragment_selector']],
                TaskDefinition::class          => ['is_team' => true, 'always_create' => true],  // Always create new with unique name
                TaskDefinitionDirective::class => [],
                SchemaAssociation::class       => [],
            ];

            foreach ($importClasses as $class => $config) {
                $classDefinitions = $definitions[$class] ?? [];
                $this->importDefinitions($class, $config, $classDefinitions);
            }

            // Create nodes and connections
            $createdNodes = $this->createNodes($targetWorkflow, $nodes);
            $this->createConnections($targetWorkflow, $connections);

            return $createdNodes;
        });
    }

    /**
     * Calculate position offset from centroid of original nodes to paste position.
     */
    protected function calculatePositionOffset(array $nodes, array $pastePosition): void
    {
        if (empty($nodes)) {
            return;
        }

        $sumX = $sumY = 0;
        foreach ($nodes as $node) {
            $settings = $node['settings'] ?? [];
            $sumX     += $settings['x']   ?? 0;
            $sumY     += $settings['y']   ?? 0;
        }

        $centroidX = $sumX / count($nodes);
        $centroidY = $sumY / count($nodes);

        $this->positionOffset = [
            'x' => $pastePosition['x'] - $centroidX,
            'y' => $pastePosition['y'] - $centroidY,
        ];
    }

    /**
     * Import definitions for a given class.
     */
    protected function importDefinitions(string $class, array $config, array $definitions): void
    {
        $isTeam       = !empty($config['is_team']);
        $matchFields  = $config['match_fields'] ?? [];
        $alwaysCreate = !empty($config['always_create']);

        foreach ($definitions as $sourceId => $definition) {
            $sourceRef = $class . ':' . $sourceId;

            // Resolve any references in the definition to already-imported IDs
            $resolvedDefinition = $this->resolveDefinitionReferences($definition);

            if ($alwaysCreate) {
                // Always create new with unique name (for objects that should never be shared)
                $object = $this->createObjectWithUniqueName($class, $resolvedDefinition, $isTeam);
            } elseif ($isTeam && !empty($matchFields)) {
                // Team-owned object with matching logic
                $object = $this->findOrCreateTeamObject($class, $resolvedDefinition, $matchFields);
            } else {
                // Child object (no matching, always create new)
                $object = $this->createObject($class, $resolvedDefinition, $isTeam);
            }

            // Store mapping for later reference resolution
            $this->importedIdMap[$sourceRef] = $object->id;
        }
    }

    /**
     * Find or create a team-owned object with name+content matching.
     * - If no existing object with same name: create with original name
     * - If existing object with same name AND same content: reuse it
     * - If existing object with same name BUT different content: create with unique name
     */
    protected function findOrCreateTeamObject(string $class, array $definition, array $matchFields): Model
    {
        $name = $definition['name'] ?? null;

        if (!$name) {
            // No name field - just create new
            return $this->createObject($class, $definition, true);
        }

        // Find existing by name in current team
        $existing = $class::where('team_id', team()->id)
            ->where('name', $name)
            ->first();

        if (!$existing) {
            // No match by name - create with original name
            return $this->createObject($class, $definition, true);
        }

        // Compare content fields
        if ($this->contentMatches($existing, $definition, $matchFields)) {
            // Name AND content match - reuse existing
            return $existing;
        }

        // Name matches but content differs - create with unique name
        $definition['name'] = ModelHelper::getNextModelName(
            (new $class)->fill(['name' => $name]),
            'name',
            ['team_id' => team()->id]
        );

        return $this->createObject($class, $definition, true);
    }

    /**
     * Check if existing object's content matches definition's content.
     */
    protected function contentMatches(Model $existing, array $definition, array $matchFields): bool
    {
        foreach ($matchFields as $field) {
            if ($field === 'name') {
                continue; // Already matched by name
            }

            $existingValue   = $existing->$field;
            $definitionValue = $definition[$field] ?? null;

            // Normalize JSON/array fields for comparison
            if (is_array($existingValue) || is_array($definitionValue)) {
                if (json_encode($existingValue) !== json_encode($definitionValue)) {
                    return false;
                }
            } elseif ($existingValue !== $definitionValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a new object from definition.
     */
    protected function createObject(string $class, array $definition, bool $isTeam): Model
    {
        $object = new $class;

        if ($isTeam) {
            $object->team_id = team()->id;
        }

        foreach ($definition as $key => $value) {
            $object->$key = $value;
        }

        $object->save();

        return $object;
    }

    /**
     * Create a new object with a unique name (for objects that should never be shared).
     */
    protected function createObjectWithUniqueName(string $class, array $definition, bool $isTeam): Model
    {
        $name = $definition['name'] ?? null;

        if ($name) {
            // Generate a unique name by checking existing names in the team
            $definition['name'] = ModelHelper::getNextModelName(
                (new $class)->fill(['name' => $name]),
                'name',
                ['team_id' => team()->id]
            );
        }

        return $this->createObject($class, $definition, $isTeam);
    }

    /**
     * Resolve references in definition to already-imported IDs.
     */
    protected function resolveDefinitionReferences(array $definition): array
    {
        foreach ($definition as $key => $value) {
            if ($value && is_string($value) && str_contains($value, ':')) {
                $resolvedId = $this->importedIdMap[$value] ?? null;
                if ($resolvedId) {
                    $definition[$key] = $resolvedId;
                }
            }
        }

        return $definition;
    }

    /**
     * Create WorkflowNodes from node definitions.
     */
    protected function createNodes(WorkflowDefinition $workflow, array $nodes): array
    {
        $createdNodes = [];

        foreach ($nodes as $nodeData) {
            $taskDefinitionRef = $nodeData['task_definition_ref'];
            $taskDefinitionId  = $this->importedIdMap[$taskDefinitionRef] ?? null;

            if (!$taskDefinitionId) {
                throw new ValidationError("Task definition not found for reference: $taskDefinitionRef");
            }

            // Adjust position
            $settings       = $nodeData['settings'] ?? [];
            $settings['x']  = ($settings['x'] ?? 0) + $this->positionOffset['x'];
            $settings['y']  = ($settings['y'] ?? 0) + $this->positionOffset['y'];

            $node                           = new WorkflowNode();
            $node->workflow_definition_id   = $workflow->id;
            $node->task_definition_id       = $taskDefinitionId;
            $node->name                     = $nodeData['name'];
            $node->settings                 = $settings;
            $node->params                   = $nodeData['params'] ?? null;
            $node->save();

            // Store mapping from export_key to new node ID
            $this->nodeIdMap[$nodeData['export_key']] = $node->id;
            $createdNodes[]                           = $node;
        }

        return $createdNodes;
    }

    /**
     * Create WorkflowConnections from connection definitions.
     */
    protected function createConnections(WorkflowDefinition $workflow, array $connections): void
    {
        foreach ($connections as $connData) {
            $sourceNodeId = $this->nodeIdMap[$connData['source_export_key']] ?? null;
            $targetNodeId = $this->nodeIdMap[$connData['target_export_key']] ?? null;

            if (!$sourceNodeId || !$targetNodeId) {
                continue; // Skip if nodes not found
            }

            WorkflowConnection::create([
                'workflow_definition_id' => $workflow->id,
                'source_node_id'         => $sourceNodeId,
                'target_node_id'         => $targetNodeId,
                'name'                   => $connData['name'],
                'source_output_port'     => $connData['source_output_port'],
                'target_input_port'      => $connData['target_input_port'],
            ]);
        }
    }
}
