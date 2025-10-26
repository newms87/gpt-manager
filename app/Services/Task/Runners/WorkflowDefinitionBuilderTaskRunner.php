<?php

namespace App\Services\Task\Runners;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Workflow\WorkflowDefinition;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\WorkflowBuilder\WorkflowBuilderDocumentationService;
use Exception;

class WorkflowDefinitionBuilderTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Workflow Organization Analysis';

    public function prepareProcess(): void
    {
        $this->taskProcess->name = static::RUNNER_NAME;

        // Timeout is configured on the TaskDefinition and accessed via relationship

        $this->activity('Preparing workflow organization analysis', 1);
    }

    public function run(): void
    {
        $this->activity('Loading orchestrator context', 10);

        // Get input data from artifacts
        $inputData       = $this->extractInputFromArtifacts();
        $currentWorkflow = $this->resolveCurrentWorkflow();

        // Load documentation context
        $context = app(WorkflowBuilderDocumentationService::class)
            ->getOrchestratorContext($currentWorkflow);

        $this->activity('Building orchestrator prompt', 20);

        // Build comprehensive prompt
        $prompt = $this->buildOrchestratorPrompt($inputData, $currentWorkflow, $context);

        $this->activity('Running agent thread with organization schema', 30);

        // Run AgentThreadTaskRunner with organization schema
        $artifact = $this->runAgentThreadWithOrganizationSchema($prompt);

        if ($artifact) {
            $this->activity('Processing workflow organization results', 80);

            // Process and split artifacts per task definition change
            $outputArtifacts = $this->processOrganizationResults($artifact);

            $this->activity('Workflow organization analysis completed', 100);
            $this->complete($outputArtifacts);
        } else {
            $this->activity('No response from workflow organization analysis', 100);
            $this->complete([]);
        }
    }

    /**
     * Extract input data from process input artifacts
     */
    protected function extractInputFromArtifacts(): array
    {
        $inputData = [
            'user_input'     => '',
            'approved_plan'  => '',
            'workflow_state' => null,
        ];

        foreach ($this->taskProcess->inputArtifacts as $artifact) {
            if ($artifact->text_content) {
                // Try to identify the type of input based on artifact name or content
                $name = strtolower($artifact->name ?? '');

                if (str_contains($name, 'input') || str_contains($name, 'requirement')) {
                    $inputData['user_input'] = $artifact->text_content;
                } elseif (str_contains($name, 'plan') || str_contains($name, 'approved')) {
                    $inputData['approved_plan'] = $artifact->text_content;
                } else {
                    // Default to user input if unclear
                    $inputData['user_input'] .= "\n\n" . $artifact->text_content;
                }
            }

            if ($artifact->json_content) {
                $inputData['workflow_state'] = $artifact->json_content;
            }
        }

        // Clean up inputs
        $inputData['user_input']    = trim($inputData['user_input']);
        $inputData['approved_plan'] = trim($inputData['approved_plan']);

        return $inputData;
    }

    /**
     * Resolve the current workflow definition if available
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
     * Build comprehensive prompt for orchestrator
     */
    public function buildOrchestratorPrompt(array $input, ?WorkflowDefinition $currentWorkflow, string $context): string
    {
        $prompt = [];

        // Add documentation context
        $prompt[] = "# Workflow Builder Documentation Context\n";
        $prompt[] = $context;
        $prompt[] = "\n---\n";

        // Add user intent and requirements
        $prompt[] = "# User Requirements\n";
        if ($input['user_input']) {
            $prompt[] = '**Original Request:**';
            $prompt[] = $input['user_input'];
            $prompt[] = '';
        }

        if ($input['approved_plan']) {
            $prompt[] = '**Approved Plan:**';
            $prompt[] = $input['approved_plan'];
            $prompt[] = '';
        }

        // Add current workflow state context
        if ($currentWorkflow) {
            $prompt[] = "# Current Workflow State\n";
            $prompt[] = 'You are modifying an existing workflow. Consider the current structure when making changes.';
            $prompt[] = "Only modify what is necessary to fulfill the user's requirements.";
            $prompt[] = '';
        } else {
            $prompt[] = "# New Workflow Creation\n";
            $prompt[] = 'You are creating a brand new workflow from scratch.';
            $prompt[] = '';
        }

        // Add workflow state data if available
        if ($input['workflow_state']) {
            $prompt[] = '**Current Workflow Data:**';
            $prompt[] = '```json';
            $prompt[] = json_encode($input['workflow_state'], JSON_PRETTY_PRINT);
            $prompt[] = '```';
            $prompt[] = '';
        }

        // Add orchestrator instructions
        $prompt[] = "# Your Task\n";
        $prompt[] = 'Analyze the requirements and break them down into specific task definitions.';
        $prompt[] = 'Your response should define the complete workflow structure including:';
        $prompt[] = '1. Task definitions with appropriate runners, agents, and prompts';
        $prompt[] = '2. Workflow connections showing data flow between tasks';
        $prompt[] = '3. Proper artifact flow modes for each task';
        $prompt[] = '4. Node positioning and organization';
        $prompt[] = '';
        $prompt[] = 'Output your analysis in split mode so each task can be processed individually in parallel.';
        $prompt[] = 'Each task specification should be complete and independent.';

        // Add examples and constraints
        $prompt[] = "\n# Important Constraints\n";
        $prompt[] = '- Use only documented task runners from the catalog';
        $prompt[] = '- Select appropriate agents based on task requirements';
        $prompt[] = '- Ensure proper artifact flow between connected tasks';
        $prompt[] = '- Follow established naming and description conventions';
        $prompt[] = '- Consider performance implications of parallel processing';

        return implode("\n", $prompt);
    }

    /**
     * Run agent thread with organization schema
     */
    protected function runAgentThreadWithOrganizationSchema(string $prompt): ?Artifact
    {
        // Get organization schema for workflow building
        $schemaDefinition = $this->getOrganizationSchemaDefinition();

        if (!$schemaDefinition) {
            throw new Exception('Organization schema definition not found for workflow building');
        }

        // Create temporary agent thread for this analysis
        $agentThread = app(AgentThreadTaskRunner::class)
            ->setTaskRun($this->taskRun)
            ->setTaskProcess($this->taskProcess)
            ->setupAgentThread($this->taskProcess->inputArtifacts()->get());

        // Add the orchestrator prompt as the initial message
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
                'name'               => 'Workflow Organization Analysis',
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
     * Get or create the schema definition for workflow organization
     */
    protected function getOrganizationSchemaDefinition(): ?SchemaDefinition
    {
        // Look for existing organization schema
        $schema = SchemaDefinition::where('team_id', $this->taskRun->taskDefinition->team_id)
            ->where('name', 'Workflow Organization Schema')
            ->first();

        if (!$schema) {
            // Create basic schema if it doesn't exist
            $schema = SchemaDefinition::create([
                'name'    => 'Workflow Organization Schema',
                'team_id' => $this->taskRun->taskDefinition->team_id,
                'schema'  => [
                    'type'       => 'object',
                    'title'      => 'WorkflowOrganization',
                    'properties' => [
                        'workflow_definition' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'        => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'max_workers' => ['type' => 'integer'],
                            ],
                        ],
                        'task_specifications' => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'name'               => ['type' => 'string'],
                                    'description'        => ['type' => 'string'],
                                    'runner_type'        => ['type' => 'string'],
                                    'agent_requirements' => ['type' => 'string'],
                                    'prompt'             => ['type' => 'string'],
                                    'configuration'      => ['type' => 'object'],
                                ],
                            ],
                        ],
                        'connections' => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'source'      => ['type' => 'string'],
                                    'target'      => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'required' => ['workflow_definition', 'task_specifications'],
                ],
            ]);
        }

        return $schema;
    }

    /**
     * Process organization results and split into individual task artifacts
     */
    protected function processOrganizationResults(Artifact $organizationArtifact): array
    {
        $artifacts = [];

        if (!$organizationArtifact->json_content) {
            static::log('No JSON content found in organization artifact');

            return [$organizationArtifact];
        }

        $organizationData = $organizationArtifact->json_content;

        // Extract task specifications for split mode output
        $taskSpecs = $organizationData['task_specifications'] ?? [];

        if (empty($taskSpecs)) {
            static::log('No task specifications found in organization data');

            return [$organizationArtifact];
        }

        static::log('Processing ' . count($taskSpecs) . ' task specifications for split mode');

        // Create individual artifacts for each task specification
        foreach ($taskSpecs as $index => $taskSpec) {
            $taskArtifact = new Artifact([
                'name'               => $taskSpec['name'] ?? 'Task Specification ' . ($index + 1),
                'task_definition_id' => $this->taskDefinition->id,
                'task_process_id'    => $this->taskProcess->id,
            ]);

            // Include the full workflow context with this specific task
            $taskArtifact->json_content = [
                'workflow_definition' => $organizationData['workflow_definition'] ?? null,
                'connections'         => $organizationData['connections']         ?? [],
                'task_specification'  => $taskSpec,
                'task_index'          => $index,
            ];

            // Add descriptive text content
            $taskArtifact->text_content = $this->formatTaskSpecificationText($taskSpec, $index);
            $taskArtifact->position     = $index;

            $taskArtifact->save();
            $artifacts[] = $taskArtifact;
        }

        static::log('Created ' . count($artifacts) . ' task specification artifacts for split processing');

        return $artifacts;
    }

    /**
     * Format task specification as readable text
     */
    protected function formatTaskSpecificationText(array $taskSpec, int $index): string
    {
        $text   = [];
        $text[] = '# Task Specification ' . ($index + 1);
        $text[] = '';

        if (isset($taskSpec['name'])) {
            $text[] = '**Name:** ' . $taskSpec['name'];
        }

        if (isset($taskSpec['description'])) {
            $text[] = '**Description:** ' . $taskSpec['description'];
        }

        if (isset($taskSpec['runner_type'])) {
            $text[] = '**Runner Type:** ' . $taskSpec['runner_type'];
        }

        if (isset($taskSpec['agent_requirements'])) {
            $text[] = '**Agent Requirements:** ' . $taskSpec['agent_requirements'];
        }

        if (isset($taskSpec['prompt'])) {
            $text[] = '';
            $text[] = '**Prompt:**';
            $text[] = $taskSpec['prompt'];
        }

        if (isset($taskSpec['configuration']) && !empty($taskSpec['configuration'])) {
            $text[] = '';
            $text[] = '**Configuration:**';
            $text[] = '```json';
            $text[] = json_encode($taskSpec['configuration'], JSON_PRETTY_PRINT);
            $text[] = '```';
        }

        return implode("\n", $text);
    }
}
