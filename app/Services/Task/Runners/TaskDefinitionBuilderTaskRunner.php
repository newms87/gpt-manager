<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\WorkflowBuilder\WorkflowBuilderDocumentationService;
use Exception;
use Illuminate\Support\Facades\DB;

class TaskDefinitionBuilderTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Task Definition Builder';

    public function prepareProcess(): void
    {
        $this->taskProcess->name = static::RUNNER_NAME;

        // Timeout is configured on the TaskDefinition and accessed via relationship

        $this->activity('Preparing task definition building', 1);
    }

    public function run(): void
    {
        $this->activity('Processing task specification artifact', 10);

        // Get single task specification from input artifact (split mode)
        $specification = $this->extractTaskSpecificationFromArtifact();

        if (!$specification) {
            $this->activity('No valid task specification found', 100);
            $this->complete([]);

            return;
        }

        // Resolve workflow context
        $workflow = $this->resolveCurrentWorkflow();

        $this->activity('Loading task builder context', 20);

        // Load task-specific documentation context
        $context = $workflow
            ? app(WorkflowBuilderDocumentationService::class)->getTaskBuilderContext($specification, $workflow)
            : 'No workflow context available';

        $this->activity('Building task-focused prompt', 30);

        // Build focused prompt for single task
        $prompt = $this->buildTaskPrompt($specification, $context);

        $this->activity('Running agent thread with task builder schema', 40);

        // Run AgentThread with task builder schema
        $artifact = $this->runAgentThreadWithTaskBuilderSchema($prompt);

        if ($artifact && $artifact->json_content) {
            $this->activity('Applying task definition to database', 80);

            // Apply the task definition to the database
            $appliedArtifact = $this->applyTaskDefinition($specification, $artifact->json_content);

            $this->activity('Task definition building completed', 100);
            $this->complete($appliedArtifact ? [$appliedArtifact] : []);
        } else {
            $this->activity('No response from task builder', 100);
            $this->complete([]);
        }
    }

    /**
     * Extract task specification from input artifact (split mode)
     */
    protected function extractTaskSpecificationFromArtifact(): ?array
    {
        foreach ($this->taskProcess->inputArtifacts as $artifact) {
            if ($artifact->json_content) {
                // Task specification should contain the task_specification field
                if (isset($artifact->json_content['task_specification'])) {
                    static::log("Found task specification in artifact: {$artifact->name}");

                    return $artifact->json_content;
                }
            }
        }

        static::log('No valid task specification found in input artifacts');

        return null;
    }

    /**
     * Resolve the current workflow definition
     */
    protected function resolveCurrentWorkflow(): ?WorkflowDefinition
    {
        // Try to get workflow from task run context
        $workflowRun = $this->taskRun->workflowRun ?? null;
        if ($workflowRun && $workflowRun->workflowDefinition) {
            return $workflowRun->workflowDefinition;
        }

        // Could also check for workflow ID in task configuration
        $workflowId = $this->config('workflow_definition_id');
        if ($workflowId) {
            return WorkflowDefinition::find($workflowId);
        }

        return null;
    }

    /**
     * Build focused prompt for single task
     */
    public function buildTaskPrompt(array $specification, string $context): string
    {
        $prompt = [];

        // Add documentation context
        $prompt[] = "# Task Builder Documentation Context\n";
        $prompt[] = $context;
        $prompt[] = "\n---\n";

        // Add task specification
        $prompt[] = "# Task Specification to Build\n";

        $taskSpec = $specification['task_specification'] ?? [];
        $prompt[] = '**Task Name:** ' . ($taskSpec['name'] ?? 'Unknown Task');

        if (isset($taskSpec['description'])) {
            $prompt[] = '**Description:** ' . $taskSpec['description'];
        }

        if (isset($taskSpec['runner_type'])) {
            $prompt[] = '**Required Runner:** ' . $taskSpec['runner_type'];
        }

        if (isset($taskSpec['agent_requirements'])) {
            $prompt[] = '**Agent Requirements:** ' . $taskSpec['agent_requirements'];
        }

        if (isset($taskSpec['prompt'])) {
            $prompt[] = '**Prompt Requirements:** ' . $taskSpec['prompt'];
        }

        if (isset($taskSpec['configuration'])) {
            $prompt[] = '**Configuration:** ' . json_encode($taskSpec['configuration'], JSON_PRETTY_PRINT);
        }

        $prompt[] = '';

        // Add workflow context
        if (isset($specification['workflow_definition'])) {
            $prompt[] = "# Workflow Context\n";
            $workflow = $specification['workflow_definition'];
            $prompt[] = '**Workflow Name:** ' . ($workflow['name'] ?? 'Unknown Workflow');

            if (isset($workflow['description'])) {
                $prompt[] = '**Workflow Description:** ' . $workflow['description'];
            }
            $prompt[] = '';
        }

        // Add connections context
        if (isset($specification['connections']) && !empty($specification['connections'])) {
            $prompt[] = "# Workflow Connections\n";
            $prompt[] = 'This task is part of a larger workflow with the following connections:';
            foreach ($specification['connections'] as $connection) {
                $prompt[] = "- {$connection['source']} → {$connection['target']}";
            }
            $prompt[] = '';
        }

        // Add task index context
        if (isset($specification['task_index'])) {
            $prompt[] = '**Task Position:** ' . ($specification['task_index'] + 1) . ' in the workflow sequence';
            $prompt[] = '';
        }

        // Add task builder instructions
        $prompt[] = "# Your Task\n";
        $prompt[] = 'Create a complete TaskDefinition based on the specification above.';
        $prompt[] = 'Your response must include all necessary properties:';
        $prompt[] = '1. Basic properties (name, description, runner)';
        $prompt[] = '2. Agent selection based on requirements';
        $prompt[] = '3. Detailed prompt following best practices';
        $prompt[] = '4. Proper configuration for the runner type';
        $prompt[] = '5. Artifact flow modes appropriate for workflow connections';
        $prompt[] = '6. Any required directives or schema definitions';
        $prompt[] = '';

        // Add important constraints
        $prompt[] = "# Important Constraints\n";
        $prompt[] = '- Use only documented task runners and agents';
        $prompt[] = '- Follow prompt engineering best practices';
        $prompt[] = '- Ensure compatibility with workflow connections';
        $prompt[] = '- Configure proper timeout and artifact modes';
        $prompt[] = '- Include team-based access control';
        $prompt[] = '- Create a complete, ready-to-use task definition';

        return implode("\n", $prompt);
    }

    /**
     * Run agent thread with task builder schema
     */
    protected function runAgentThreadWithTaskBuilderSchema(string $prompt): ?Artifact
    {
        // Get task builder schema for individual task creation
        $schemaDefinition = $this->getTaskBuilderSchemaDefinition();

        if (!$schemaDefinition) {
            throw new Exception('Task builder schema definition not found');
        }

        // Create temporary agent thread for this task building
        $agentThread = app(AgentThreadTaskRunner::class)
            ->setTaskRun($this->taskRun)
            ->setTaskProcess($this->taskProcess)
            ->setupAgentThread($this->taskProcess->inputArtifacts()->get());

        // Add the task builder prompt as the initial message
        $agentThread->messages()->create([
            'role'    => 'user',
            'content' => $prompt,
            'team_id' => $this->taskRun->taskDefinition->team_id,
        ]);

        // Get timeout from configuration
        $timeout = $this->config('timeout');
        if ($timeout !== null) {
            $timeout = (int)$timeout;
            $timeout = max(1, min($timeout, 600)); // Ensure between 1 and 600 seconds
        }

        // Run the agent thread with schema validation
        $jsonSchemaService = app(JsonSchemaService::class)->useArtifactMeta()->includeNullValues();

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($schemaDefinition, null, $jsonSchemaService)
            ->withTimeout($timeout)
            ->run($agentThread);

        if ($threadRun->lastMessage) {
            // Create artifact from the response
            $artifact = new Artifact([
                'name'               => 'Task Definition Build Result',
                'task_definition_id' => $this->taskDefinition->id,
                'task_process_id'    => $this->taskProcess->id,
            ]);

            // Store the JSON response
            if ($threadRun->lastMessage->json_content) {
                $artifact->json_content = $threadRun->lastMessage->json_content;
            }

            // Also store any text content
            if ($threadRun->lastMessage->content) {
                $artifact->text_content = $threadRun->lastMessage->content;
            }

            $artifact->schemaDefinition()->associate($schemaDefinition);
            $artifact->save();

            return $artifact;
        }

        return null;
    }

    /**
     * Get or create the schema definition for task building
     */
    protected function getTaskBuilderSchemaDefinition(): ?SchemaDefinition
    {
        // Look for existing task builder schema
        $schema = SchemaDefinition::where('team_id', $this->taskRun->taskDefinition->team_id)
            ->where('name', 'Task Builder Schema')
            ->first();

        if (!$schema) {
            // Create task builder schema if it doesn't exist
            $schema = SchemaDefinition::create([
                'name'    => 'Task Builder Schema',
                'team_id' => $this->taskRun->taskDefinition->team_id,
                'schema'  => [
                    'type'       => 'object',
                    'title'      => 'TaskDefinitionBuilder',
                    'properties' => [
                        'action' => [
                            'type'        => 'string',
                            'enum'        => ['create', 'update', 'delete'],
                            'description' => 'Action to perform on the task definition',
                        ],
                        'task_definition' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'                   => ['type' => 'string', 'maxLength' => 80],
                                'description'            => ['type' => 'string'],
                                'prompt'                 => ['type' => 'string'],
                                'task_runner_name'       => ['type' => 'string'],
                                'task_runner_config'     => ['type' => 'object'],
                                'response_format'        => ['type' => 'string'],
                                'input_artifact_mode'    => ['type' => 'string'],
                                'input_artifact_levels'  => ['type' => 'array', 'items' => ['type' => 'integer']],
                                'output_artifact_mode'   => ['type' => 'string'],
                                'output_artifact_levels' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                'timeout_after_seconds'  => ['type' => 'integer'],
                                'agent_name'             => ['type' => 'string'],
                            ],
                            'required' => ['name', 'description', 'task_runner_name'],
                        ],
                        'directives' => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'name'     => ['type' => 'string'],
                                    'content'  => ['type' => 'string'],
                                    'section'  => ['type' => 'string', 'enum' => ['Top', 'Bottom']],
                                    'position' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                        'workflow_node' => [
                            'type'       => 'object',
                            'properties' => [
                                'x'              => ['type' => 'number'],
                                'y'              => ['type' => 'number'],
                                'position_notes' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'required' => ['action', 'task_definition'],
                ],
            ]);
        }

        return $schema;
    }

    /**
     * Apply task definition to database (create/update/delete operations)
     */
    protected function applyTaskDefinition(array $specification, array $result): ?Artifact
    {
        return DB::transaction(function () use ($result) {
            $action         = $result['action']          ?? 'create';
            $taskDefData    = $result['task_definition'] ?? [];
            $directivesData = $result['directives']      ?? [];
            $nodeData       = $result['workflow_node']   ?? [];

            static::log("Applying task definition with action: {$action}");

            // Resolve agent if specified
            $agent = null;
            if (!empty($taskDefData['agent_name'])) {
                $agent = Agent::where('team_id', $this->taskRun->taskDefinition->team_id)
                    ->where('name', $taskDefData['agent_name'])
                    ->first();

                if (!$agent) {
                    // Find first available agent as fallback
                    $agent = Agent::where('team_id', $this->taskRun->taskDefinition->team_id)->first();
                }
            }

            $taskDefinition = null;
            $workflow       = $this->resolveCurrentWorkflow();

            switch ($action) {
                case 'create':
                    $taskDefinition = $this->createTaskDefinition($taskDefData, $agent, $directivesData);
                    if ($workflow && !empty($nodeData)) {
                        $this->createWorkflowNode($workflow, $taskDefinition, $nodeData);
                    }
                    break;

                case 'update':
                    $taskDefinition = $this->updateTaskDefinition($taskDefData, $agent, $directivesData);
                    if ($taskDefinition && $workflow && !empty($nodeData)) {
                        $this->updateWorkflowNode($workflow, $taskDefinition, $nodeData);
                    }
                    break;

                case 'delete':
                    $this->deleteTaskDefinition($taskDefData);
                    break;
            }

            // Create result artifact
            $artifact = new Artifact([
                'name'               => "Applied Task Definition: {$action}",
                'task_definition_id' => $this->taskDefinition->id,
                'task_process_id'    => $this->taskProcess->id,
                'text_content'       => $this->formatAppliedResultText($action, $taskDefData, $taskDefinition),
                'json_content'       => [
                    'action'                => $action,
                    'task_definition_id'    => $taskDefinition?->id,
                    'applied_data'          => $taskDefData,
                    'workflow_node_created' => !empty($nodeData),
                    'directives_count'      => count($directivesData),
                ],
            ]);

            $artifact->save();
            static::log("Applied task definition successfully: {$action}");

            return $artifact;
        });
    }

    /**
     * Create new task definition
     */
    protected function createTaskDefinition(array $data, ?Agent $agent, array $directivesData): TaskDefinition
    {
        $taskDefinition = new TaskDefinition(array_merge($data, [
            'agent_id' => $agent?->id,
        ]));
        $taskDefinition->team_id = $this->taskRun->taskDefinition->team_id;
        $taskDefinition->save();

        $this->createTaskDefinitionDirectives($taskDefinition, $directivesData);

        static::log("Created task definition: {$taskDefinition->name} (ID: {$taskDefinition->id})");

        return $taskDefinition;
    }

    /**
     * Update existing task definition
     */
    protected function updateTaskDefinition(array $data, ?Agent $agent, array $directivesData): ?TaskDefinition
    {
        $taskDefinition = TaskDefinition::where('team_id', $this->taskRun->taskDefinition->team_id)
            ->where('name', $data['name'])
            ->first();

        if (!$taskDefinition) {
            // If not found, create new one
            return $this->createTaskDefinition($data, $agent, $directivesData);
        }

        $taskDefinition->update(array_merge($data, [
            'agent_id' => $agent?->id,
        ]));

        // Update directives (remove old ones and create new ones)
        $taskDefinition->taskDefinitionDirectives()->delete();
        $this->createTaskDefinitionDirectives($taskDefinition, $directivesData);

        static::log("Updated task definition: {$taskDefinition->name} (ID: {$taskDefinition->id})");

        return $taskDefinition;
    }

    /**
     * Delete task definition
     */
    protected function deleteTaskDefinition(array $data): void
    {
        $taskDefinition = TaskDefinition::where('team_id', $this->taskRun->taskDefinition->team_id)
            ->where('name', $data['name'])
            ->first();

        if ($taskDefinition) {
            $taskDefinition->delete();
            static::log("Deleted task definition: {$taskDefinition->name} (ID: {$taskDefinition->id})");
        } else {
            static::log("Task definition not found for deletion: {$data['name']}");
        }
    }

    /**
     * Create task definition directives
     */
    protected function createTaskDefinitionDirectives(TaskDefinition $taskDefinition, array $directivesData): void
    {
        foreach ($directivesData as $index => $directiveData) {
            if (empty($directiveData['content'])) {
                continue;
            }

            // For now, we'll store the directive content directly in the task definition directives
            // In a full implementation, you'd want to create/find PromptDirective records first
            TaskDefinitionDirective::create([
                'task_definition_id' => $taskDefinition->id,
                'section'            => $directiveData['section']  ?? TaskDefinitionDirective::SECTION_TOP,
                'position'           => $directiveData['position'] ?? $index,
                // Note: This is simplified - normally you'd create PromptDirective first
                // and then reference it here via prompt_directive_id
            ]);
        }
    }

    /**
     * Create workflow node for task definition
     */
    protected function createWorkflowNode(WorkflowDefinition $workflow, TaskDefinition $taskDefinition, array $nodeData): void
    {
        $settings = [];
        if (isset($nodeData['x'])) {
            $settings['x'] = $nodeData['x'];
        }
        if (isset($nodeData['y'])) {
            $settings['y'] = $nodeData['y'];
        }

        $node = new WorkflowNode([
            'task_definition_id' => $taskDefinition->id,
            'name'               => $taskDefinition->name,
            'settings'           => $settings,
        ]);

        // Set workflow_definition_id directly since it's not fillable
        $node->workflow_definition_id = $workflow->id;
        $node->save();

        static::log("Created workflow node for task: {$taskDefinition->name}");
    }

    /**
     * Update workflow node for task definition
     */
    protected function updateWorkflowNode(WorkflowDefinition $workflow, TaskDefinition $taskDefinition, array $nodeData): void
    {
        $node = WorkflowNode::where('workflow_definition_id', $workflow->id)
            ->where('task_definition_id', $taskDefinition->id)
            ->first();

        if ($node) {
            $settings = $node->settings ?? [];
            if (isset($nodeData['x'])) {
                $settings['x'] = $nodeData['x'];
            }
            if (isset($nodeData['y'])) {
                $settings['y'] = $nodeData['y'];
            }

            $node->update([
                'settings' => $settings,
            ]);
            static::log("Updated workflow node for task: {$taskDefinition->name}");
        } else {
            $this->createWorkflowNode($workflow, $taskDefinition, $nodeData);
        }
    }

    /**
     * Format applied result as readable text
     */
    protected function formatAppliedResultText(string $action, array $data, ?TaskDefinition $taskDefinition): string
    {
        $text   = [];
        $text[] = "# Task Definition {$action} Applied";
        $text[] = '';
        $text[] = "**Action:** {$action}";
        $text[] = '**Task Name:** ' . ($data['name'] ?? 'Unknown');

        if ($taskDefinition) {
            $text[] = "**Database ID:** {$taskDefinition->id}";
        }

        if (!empty($data['description'])) {
            $text[] = '**Description:** ' . $data['description'];
        }

        if (!empty($data['task_runner_name'])) {
            $text[] = '**Runner:** ' . $data['task_runner_name'];
        }

        $text[] = '';
        $text[] = 'The task definition has been successfully applied to the database.';

        if ($taskDefinition && $action !== 'delete') {
            $text[] = 'You can now use this task in workflows or run it independently.';
        }

        return implode("\n", $text);
    }
}
