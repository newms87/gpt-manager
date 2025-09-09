<?php

namespace App\Services\WorkflowBuilder;

use App\Models\Workflow\WorkflowDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;

class WorkflowBuilderDocumentationService
{
    private array $documentCache = [];
    private string $docsBasePath;

    public function __construct()
    {
        $this->docsBasePath = base_path('docs/workflow-builder-prompts');
    }

    /**
     * Get context for planning conversations and requirements gathering
     */
    public function getPlanningContext(WorkflowDefinition $workflow = null): string
    {
        $context = [];

        // Load high-level workflow concepts
        $context[] = $this->loadDocumentFile('workflow-definition.md');
        $context[] = $this->loadDocumentFile('task-definition.md');
        $context[] = $this->loadDocumentFile('workflow-connections.md');

        // Include current workflow structure if modifying existing
        if ($workflow) {
            $context[] = $this->formatCurrentWorkflowContext($workflow);
        }

        // Add planning guidance
        $context[] = "## Planning Guidelines\n\n";
        $context[] = "Focus on understanding the user's high-level requirements.\n";
        $context[] = "Ask clarifying questions about workflow goals, inputs, outputs, and constraints.\n";
        $context[] = "Propose a conceptual workflow structure before detailed implementation.\n";

        return implode("\n\n", array_filter($context));
    }

    /**
     * Get context for workflow orchestrator (main building phase)
     */
    public function getOrchestratorContext(WorkflowDefinition $workflow = null): string
    {
        $context = [];

        // Load core workflow building documentation
        $context[] = $this->loadDocumentFile('workflow-definition.md');
        $context[] = $this->loadDocumentFile('task-definition.md');
        $context[] = $this->loadDocumentFile('workflow-connections.md');
        $context[] = $this->loadDocumentFile('task-runners-catalog.md');
        $context[] = $this->loadDocumentFile('agent-selection.md');

        // Include current workflow structure if exists
        if ($workflow) {
            $context[] = $this->formatCurrentWorkflowContext($workflow);
        }

        // Add orchestrator guidance
        $context[] = "## Orchestrator Guidelines\n\n";
        $context[] = "Break down the approved plan into specific task definitions.\n";
        $context[] = "Define workflow connections and data flow between tasks.\n";
        $context[] = "Output task specifications that can be individually processed.\n";
        $context[] = "Ensure proper artifact flow modes and connection patterns.\n";

        return implode("\n\n", array_filter($context));
    }

    /**
     * Get context for individual task building
     */
    public function getTaskBuilderContext(array $specification, WorkflowDefinition $workflow): string
    {
        $context = [];

        // Load task-specific documentation
        $context[] = $this->loadDocumentFile('task-definition.md');
        $context[] = $this->loadDocumentFile('task-runners-catalog.md');
        $context[] = $this->loadDocumentFile('agent-selection.md');
        $context[] = $this->loadDocumentFile('prompt-engineering-guide.md');
        $context[] = $this->loadDocumentFile('artifact-flow.md');

        // Add workflow context for connections
        $context[] = $this->formatCurrentWorkflowContext($workflow);

        // Add related task definitions for context
        $context[] = $this->formatRelatedTasksContext($workflow, $specification);

        // Add task builder guidance
        $context[] = "## Task Builder Guidelines\n\n";
        $context[] = "Focus on creating one complete task definition.\n";
        $context[] = "Choose appropriate runner type and configuration.\n";
        $context[] = "Select the best agent for the task requirements.\n";
        $context[] = "Write clear, specific prompts following best practices.\n";
        $context[] = "Configure proper artifact modes for data flow.\n";

        return implode("\n\n", array_filter($context));
    }

    /**
     * Get context for result evaluation
     */
    public function getEvaluationContext(array $artifacts): string
    {
        $context = [];

        // Load evaluation guidelines (if exists)
        $evaluationDoc = $this->loadDocumentFile('evaluation-guidelines.md');
        if ($evaluationDoc) {
            $context[] = $evaluationDoc;
        }

        // Format build artifacts for analysis
        $context[] = "## Build Artifacts\n\n";
        $context[] = $this->formatArtifactsForEvaluation($artifacts);

        // Add evaluation guidance
        $context[] = "## Evaluation Guidelines\n\n";
        $context[] = "Analyze the workflow build results for completeness and correctness.\n";
        $context[] = "Explain what was created in user-friendly terms.\n";
        $context[] = "Identify any potential issues or improvements.\n";
        $context[] = "Provide recommendations for testing and usage.\n";
        $context[] = "Suggest possible enhancements or related workflows.\n";

        return implode("\n\n", array_filter($context));
    }

    /**
     * Load a documentation file from the prompts directory
     */
    public function loadDocumentFile(string $filename): ?string
    {
        // Check memory cache first
        if (isset($this->documentCache[$filename])) {
            return $this->documentCache[$filename];
        }

        $filePath = $this->docsBasePath . '/' . $filename;

        if (!File::exists($filePath)) {
            Log::warning("Workflow builder documentation file not found: {$filename}", [
                'path' => $filePath,
                'expected_files' => [
                    'workflow-definition.md',
                    'task-definition.md', 
                    'workflow-connections.md',
                    'task-runners-catalog.md',
                    'agent-selection.md',
                    'prompt-engineering-guide.md',
                    'artifact-flow.md'
                ]
            ]);

            // Return a reasonable default or placeholder
            return $this->getDefaultDocumentContent($filename);
        }

        try {
            $content = File::get($filePath);
            
            if (empty(trim($content))) {
                Log::warning("Workflow builder documentation file is empty: {$filename}");
                return $this->getDefaultDocumentContent($filename);
            }

            // Cache in memory for performance
            $this->documentCache[$filename] = $content;

            return $content;
        } catch (\Exception $e) {
            Log::error("Failed to read workflow builder documentation file: {$filename}", [
                'error' => $e->getMessage(),
                'path' => $filePath
            ]);

            throw new ValidationError("Failed to load documentation file: {$filename}", 500);
        }
    }

    /**
     * Format current workflow state for context
     */
    protected function formatCurrentWorkflowContext(WorkflowDefinition $workflow): string
    {
        $workflow->load([
            'workflowNodes.taskDefinition',
            'workflowConnections.sourceNode.taskDefinition',
            'workflowConnections.targetNode.taskDefinition'
        ]);

        $context = [];
        $context[] = "## Current Workflow: {$workflow->name}\n";
        
        if ($workflow->description) {
            $context[] = "**Description:** {$workflow->description}\n";
        }

        $context[] = "**Max Workers:** {$workflow->max_workers}\n";

        // Format nodes
        if ($workflow->workflowNodes->isNotEmpty()) {
            $context[] = "### Current Nodes:\n";
            foreach ($workflow->workflowNodes as $node) {
                $task = $node->taskDefinition;
                $context[] = "- **{$task->name}** (ID: {$node->id})";
                $context[] = "  - Runner: {$task->task_runner_name}";
                $context[] = "  - Position: ({$node->x}, {$node->y})";
                if ($task->description) {
                    $context[] = "  - Description: {$task->description}";
                }
            }
        }

        // Format connections
        if ($workflow->workflowConnections->isNotEmpty()) {
            $context[] = "\n### Current Connections:\n";
            foreach ($workflow->workflowConnections as $connection) {
                $sourceName = $connection->sourceNode->taskDefinition->name ?? "Unknown";
                $targetName = $connection->targetNode->taskDefinition->name ?? "Unknown";
                $context[] = "- {$sourceName} → {$targetName}";
            }
        }

        return implode("\n", $context);
    }

    /**
     * Format related tasks context for task building
     */
    protected function formatRelatedTasksContext(WorkflowDefinition $workflow, array $specification): string
    {
        $context = [];
        
        // Add information about related tasks from the specification
        if (isset($specification['related_tasks']) && !empty($specification['related_tasks'])) {
            $context[] = "### Related Tasks in This Workflow:\n";
            foreach ($specification['related_tasks'] as $relatedTask) {
                $context[] = "- **{$relatedTask['name']}**: " . ($relatedTask['description'] ?? 'No description');
            }
        }

        // Add information about existing tasks in the workflow
        if ($workflow->workflowNodes->isNotEmpty()) {
            $context[] = "\n### Existing Tasks in Workflow:\n";
            foreach ($workflow->workflowNodes as $node) {
                $task = $node->taskDefinition;
                $context[] = "- **{$task->name}** (Runner: {$task->task_runner_name})";
                if ($task->description) {
                    $context[] = "  - {$task->description}";
                }
            }
        }

        return implode("\n", array_filter($context));
    }

    /**
     * Format artifacts for evaluation analysis
     */
    protected function formatArtifactsForEvaluation(array $artifacts): string
    {
        $context = [];

        foreach ($artifacts as $index => $artifact) {
            $context[] = "### Artifact " . ($index + 1);
            
            if (isset($artifact['name'])) {
                $context[] = "**Name:** {$artifact['name']}";
            }
            
            if (isset($artifact['type'])) {
                $context[] = "**Type:** {$artifact['type']}";
            }
            
            if (isset($artifact['content'])) {
                // Format content based on type
                if (is_array($artifact['content'])) {
                    $context[] = "**Content:**";
                    $context[] = "```json";
                    $context[] = json_encode($artifact['content'], JSON_PRETTY_PRINT);
                    $context[] = "```";
                } else {
                    $context[] = "**Content:**";
                    $context[] = (string)$artifact['content'];
                }
            }
            
            $context[] = ""; // Add spacing between artifacts
        }

        return implode("\n", $context);
    }

    /**
     * Provide default content when documentation files don't exist
     */
    protected function getDefaultDocumentContent(string $filename): ?string
    {
        $defaults = [
            'workflow-definition.md' => "# Workflow Definition\n\nWorkflows organize tasks into connected processes. Key properties:\n- name: Descriptive workflow name\n- description: Purpose and functionality\n- max_workers: Concurrent task limit\n- nodes: Individual task instances\n- connections: Data flow between tasks",
            
            'task-definition.md' => "# Task Definition\n\nTasks are individual units of work. Key properties:\n- name: Task identifier\n- description: Task purpose\n- task_runner_name: Execution engine\n- prompt: Instructions for AI agents\n- agent_id: Assigned AI agent\n- input/output modes: Data handling configuration",
            
            'workflow-connections.md' => "# Workflow Connections\n\nConnections define data flow between tasks:\n- source_node_id: Origin task\n- target_node_id: Destination task\n- Connections determine execution order\n- Outputs from source become inputs to target",
            
            'task-runners-catalog.md' => "# Task Runners\n\nAvailable task runners:\n- AgentThreadTaskRunner: AI agent conversations\n- WorkflowInputTaskRunner: User input collection\n- WorkflowOutputTaskRunner: Result presentation",
            
            'agent-selection.md' => "# Agent Selection\n\nChoose agents based on task requirements:\n- General tasks: Use default agents\n- Specialized domains: Select domain-specific agents\n- Consider agent capabilities and limitations",
            
            'prompt-engineering-guide.md' => "# Prompt Engineering\n\nBest practices for task prompts:\n- Be specific and clear\n- Include context and examples\n- Define expected output format\n- Provide error handling guidance",
            
            'artifact-flow.md' => "# Artifact Flow\n\nData flow between tasks:\n- single: One artifact per task\n- split: Process artifacts individually\n- merge: Combine multiple artifacts\n- Configure based on task requirements"
        ];

        return $defaults[$filename] ?? null;
    }

    /**
     * Clear the document cache (useful for testing or cache invalidation)
     */
    public function clearCache(): void
    {
        $this->documentCache = [];
    }

    /**
     * Get all available documentation files
     */
    public function getAvailableDocuments(): array
    {
        if (!File::exists($this->docsBasePath)) {
            return [];
        }

        return File::glob($this->docsBasePath . '/*.md');
    }

    /**
     * Validate that all required documentation files exist
     */
    public function validateDocumentation(): array
    {
        $requiredFiles = [
            'workflow-definition.md',
            'task-definition.md',
            'workflow-connections.md',
            'task-runners-catalog.md',
            'agent-selection.md',
            'prompt-engineering-guide.md',
            'artifact-flow.md'
        ];

        $missing = [];
        $existing = [];

        foreach ($requiredFiles as $file) {
            $filePath = $this->docsBasePath . '/' . $file;
            if (File::exists($filePath)) {
                $existing[] = $file;
            } else {
                $missing[] = $file;
            }
        }

        return [
            'existing' => $existing,
            'missing' => $missing,
            'base_path' => $this->docsBasePath
        ];
    }
}