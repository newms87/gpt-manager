<?php

namespace App\Services\WorkflowBuilder;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Task\TaskDefinition;
use App\Models\Team\Team;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowInputRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Workflow\WorkflowRunnerService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;

class WorkflowBuilderService
{
    use HasDebugLogging;

    /**
     * Start the requirements gathering phase for workflow building
     */
    public function startRequirementsGathering(string $prompt, ?int $workflowDefinitionId = null, ?int $chatId = null, ?Team $team = null): WorkflowBuilderChat
    {
        $this->validateRequirementsGatheringInput($prompt, $workflowDefinitionId, $chatId, $team);

        // Create or retrieve existing chat
        if ($chatId) {
            $chat = $this->retrieveExistingChat($chatId);
        } else {
            $chat = $this->createNewChat($prompt, $workflowDefinitionId, $team);
        }

        // Initiate planning phase with LLM
        $this->initiatePlanningConversation($chat, $prompt);

        return $chat->fresh();
    }

    /**
     * Generate workflow plan from user input
     */
    public function generateWorkflowPlan(WorkflowBuilderChat $chat, string $userInput): array
    {
        $this->validateChatForPlanGeneration($chat);

        // Add user input to thread
        $chat->agentThread->messages()->create([
            'role'    => 'user',
            'content' => $userInput,
        ]);

        // Load existing workflow context if modifying
        $planningContext = app(WorkflowBuilderDocumentationService::class)
            ->getPlanningContext($chat->workflowDefinition);

        // Use AgentThreadService to generate plan
        $agentThreadRun = app(AgentThreadService::class)->run($chat->agentThread);

        if (!$agentThreadRun->isCompleted()) {
            throw new ValidationError('Failed to generate workflow plan', 500);
        }

        // Extract plan from response
        $plan = $this->extractPlanFromResponse($agentThreadRun->lastMessage);

        // Update chat meta with plan state (only transition if not already in analyzing_plan)
        if ($chat->status !== WorkflowBuilderChat::STATUS_ANALYZING_PLAN) {
            $chat->updatePhase(WorkflowBuilderChat::STATUS_ANALYZING_PLAN, [
                'generated_plan'    => $plan,
                'plan_generated_at' => now()->toISOString(),
            ]);
        } else {
            // Already in analyzing_plan phase, update meta directly to avoid invalid transition
            // Update plan_generated_at to reflect new plan generation, add plan_modified_at
            $meta               = $chat->meta ?? [];
            $newTimestamp       = now()->toISOString();
            $meta['phase_data'] = array_merge($meta['phase_data'] ?? [], [
                'generated_plan'    => $plan,
                'plan_generated_at' => $newTimestamp,
                'plan_modified_at'  => $newTimestamp,
            ]);
            $meta['build_state'] = array_merge($meta['build_state'] ?? [], [
                'generated_plan'    => $plan,
                'plan_generated_at' => $newTimestamp,
                'plan_modified_at'  => $newTimestamp,
            ]);
            $meta['updated_at'] = $newTimestamp;

            $chat->update(['meta' => $meta]);
        }

        return $plan;
    }

    /**
     * Start the workflow building process
     */
    public function startWorkflowBuild(WorkflowBuilderChat $chat): WorkflowRun
    {
        $this->validateChatForWorkflowBuild($chat);

        // Prepare build artifacts
        $artifacts = $this->prepareBuildArtifacts($chat);

        // Get the builder workflow definition
        $builderWorkflowDefinition = $this->getBuilderWorkflowDefinition($chat);

        // Start workflow build via WorkflowRunnerService
        $workflowRun = WorkflowRunnerService::start($builderWorkflowDefinition, $artifacts);

        // Associate workflow run with chat
        $chat->currentWorkflowRun()->associate($workflowRun);
        $chat->updatePhase(WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW, [
            'workflow_run_id'  => $workflowRun->id,
            'build_started_at' => now()->toISOString(),
        ]);

        return $workflowRun;
    }

    /**
     * Process workflow completion and apply changes
     */
    public function processWorkflowCompletion(WorkflowBuilderChat $chat, WorkflowRun $completedRun): void
    {
        $this->validateWorkflowCompletion($chat, $completedRun);

        if (!$completedRun->isCompleted()) {
            $this->handleWorkflowBuildFailure($chat, $completedRun);

            return;
        }

        // Extract build artifacts from completed workflow
        $buildArtifacts = $this->extractBuildArtifacts($completedRun);

        // Apply changes to WorkflowDefinition/TaskDefinitions
        $workflowDefinition = $this->applyWorkflowChanges($chat, $buildArtifacts);

        // Update chat with artifacts and status
        $chat->attachArtifacts($buildArtifacts);
        $chat->workflowDefinition()->associate($workflowDefinition);
        $chat->updatePhase(WorkflowBuilderChat::STATUS_EVALUATING_RESULTS, [
            'build_completed_at'     => now()->toISOString(),
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        // Trigger evaluation step
        $this->evaluateAndCommunicateResults($chat);
    }

    /**
     * Evaluate build results and communicate with user
     */
    public function evaluateAndCommunicateResults(WorkflowBuilderChat $chat): void
    {
        $this->validateChatForEvaluation($chat);

        // Create new AgentThread for result evaluation
        $evaluationThread = $this->createEvaluationAgentThread($chat);

        // Load evaluation context
        $evaluationContext = app(WorkflowBuilderDocumentationService::class)
            ->getEvaluationContext($chat->getLatestArtifacts());

        // Add evaluation context to thread
        $evaluationThread->messages()->create([
            'role'    => 'user',
            'content' => $evaluationContext,
        ]);

        // Use AgentThreadService to analyze build artifacts
        $agentThreadRun = app(AgentThreadService::class)->run($evaluationThread);

        if (!$agentThreadRun->isCompleted()) {
            throw new ValidationError('Failed to evaluate workflow build results', 500);
        }

        // Generate user-friendly summary
        $summary = $this->generateResultSummary($chat, $agentThreadRun->lastMessage);

        // Add summary to main chat thread
        $chat->addThreadMessage($summary['message'], $summary['data']);

        // Update chat with final results and complete process
        $chat->updatePhase(WorkflowBuilderChat::STATUS_COMPLETED, [
            'evaluation_completed_at' => now()->toISOString(),
            'result_summary'          => $summary,
        ]);
    }

    /**
     * Validate input parameters for requirements gathering
     */
    protected function validateRequirementsGatheringInput(string $prompt, ?int $workflowDefinitionId, ?int $chatId, ?Team $team = null): void
    {
        if (empty($prompt)) {
            throw new ValidationError('Prompt cannot be empty', 400);
        }

        $currentTeam = $team ?: team();
        if (!$currentTeam) {
            // Skip team-based validation if no team context
            return;
        }

        if ($workflowDefinitionId && !WorkflowDefinition::where('team_id', $currentTeam->id)->find($workflowDefinitionId)) {
            throw new ValidationError('Workflow definition not found or not accessible', 404);
        }

        if ($chatId && !WorkflowBuilderChat::where('team_id', $currentTeam->id)->find($chatId)) {
            throw new ValidationError('Workflow builder chat not found or not accessible', 404);
        }
    }

    /**
     * Create a new workflow builder chat
     */
    protected function createNewChat(string $prompt, ?int $workflowDefinitionId, ?Team $team = null): WorkflowBuilderChat
    {
        // Use provided team or fall back to team() helper
        $currentTeam = $team ?: team();
        if (!$currentTeam) {
            throw new ValidationError('No team context available', 400);
        }

        // Get a user for the team (needed for console context)
        $teamUser = $currentTeam->users()->first();

        // Create WorkflowInput for the prompt
        $workflowInput = app(WorkflowInputRepository::class)->createWorkflowInput([
            'name'        => 'Workflow Builder: ' . substr($prompt, 0, 50),
            'content'     => $prompt,
            'description' => 'User prompt for workflow building',
            'team_id'     => $currentTeam->id,
            'user_id'     => $teamUser?->id,
        ]);

        // Create AgentThread for the planning conversation
        $planningAgent = Agent::whereNull('team_id')->where('name', 'Workflow Planner')->first();
        if (!$planningAgent) {
            throw new ValidationError('Workflow Planner agent not found. Please run WorkflowBuilderSeeder.', 400);
        }

        $agentThread = AgentThread::create([
            'agent_id' => $planningAgent->id,
            'name'     => 'Workflow Planning: ' . substr($prompt, 0, 40),
            'team_id'  => $currentTeam->id,
        ]);

        return WorkflowBuilderChat::create([
            'workflow_input_id'      => $workflowInput->id,
            'workflow_definition_id' => $workflowDefinitionId,
            'agent_thread_id'        => $agentThread->id,
            'status'                 => WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING,
            'team_id'                => $currentTeam->id,
            'meta'                   => [
                'original_prompt' => $prompt,
                'created_at'      => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Retrieve existing chat and validate access
     */
    protected function retrieveExistingChat(int $chatId): WorkflowBuilderChat
    {
        $currentTeam = team();
        if (!$currentTeam) {
            throw new ValidationError('No team context available', 400);
        }

        $chat = WorkflowBuilderChat::where('team_id', $currentTeam->id)
            ->where('id', $chatId)
            ->first();

        if (!$chat) {
            throw new ValidationError('Chat not found or not accessible', 404);
        }

        return $chat;
    }

    /**
     * Create agent thread for planning conversation
     */
    protected function createPlanningAgentThread(WorkflowBuilderChat $chat, string $prompt): AgentThread
    {
        $planningAgent = $this->getPlanningAgent();

        $currentTeam = team();
        if (!$currentTeam) {
            throw new ValidationError('No team context available', 400);
        }

        $agentThread = AgentThread::create([
            'name'     => 'Workflow Planning: ' . substr($prompt, 0, 50),
            'agent_id' => $planningAgent->id,
            'team_id'  => $currentTeam->id,
        ]);

        return $agentThread;
    }

    /**
     * Get the planning agent for requirements gathering
     */
    protected function getPlanningAgent(): Agent
    {
        $currentTeam = team();
        if (!$currentTeam) {
            throw new ValidationError('No team context available', 400);
        }

        $agent = Agent::whereNull('team_id')
            ->where('name', 'Workflow Planner')
            ->first();

        if (!$agent) {
            throw new ValidationError('Workflow Planner agent not found. Please ensure the agent is configured.', 500);
        }

        return $agent;
    }

    /**
     * Initiate planning conversation with LLM
     */
    protected function initiatePlanningConversation(WorkflowBuilderChat $chat, string $prompt): void
    {
        $workflowDefinition = $chat->workflowDefinition;
        $planningContext    = app(WorkflowBuilderDocumentationService::class)
            ->getPlanningContext($workflowDefinition);

        // Ensure agentThread is loaded
        if (!$chat->agentThread) {
            $chat->load('agentThread');
        }

        if (!$chat->agentThread) {
            throw new ValidationError('AgentThread not found for chat', 500);
        }

        // Add initial planning context
        $chat->agentThread->messages()->create([
            'role'    => 'system',
            'content' => $planningContext,
        ]);

        // Add user prompt
        $chat->agentThread->messages()->create([
            'role'    => 'user',
            'content' => $prompt,
        ]);
    }

    /**
     * Validate chat state for plan generation
     */
    protected function validateChatForPlanGeneration(WorkflowBuilderChat $chat): void
    {
        if (!$chat->agentThread) {
            throw new ValidationError('No agent thread associated with chat', 400);
        }

        if ($chat->status !== WorkflowBuilderChat::STATUS_REQUIREMENTS_GATHERING &&
            $chat->status !== WorkflowBuilderChat::STATUS_ANALYZING_PLAN) {
            throw new ValidationError('Chat is not in a valid state for plan generation', 400);
        }
    }

    /**
     * Extract plan from agent response
     */
    protected function extractPlanFromResponse($lastMessage): array
    {
        $content     = $lastMessage->content      ?? '';
        $jsonContent = $lastMessage->json_content ?? null;

        // Try to extract structured plan from JSON content first
        if ($jsonContent && is_array($jsonContent)) {
            $plan = [
                'workflow_name' => $jsonContent['workflow_name'] ?? $jsonContent['name'] ?? 'Generated Workflow',
                'description'   => $jsonContent['description']   ?? '',
                'tasks'         => $jsonContent['tasks']         ?? $jsonContent['task_specifications'] ?? [],
                'connections'   => $jsonContent['connections']   ?? [],
                'max_workers'   => $jsonContent['max_workers']   ?? 5,
                'extracted_at'  => now()->toISOString(),
                'message_id'    => $lastMessage->id ?? null,
                'source_type'   => 'json',
            ];

            // Validate that we have essential components
            if (!empty($plan['tasks'])) {
                return $plan;
            }
        }

        // Fallback to text-based extraction if JSON is not available or incomplete
        $plan                 = $this->extractPlanFromText($content);
        $plan['extracted_at'] = now()->toISOString();
        $plan['message_id']   = $lastMessage->id ?? null;
        $plan['source_type']  = 'text';

        return $plan;
    }

    /**
     * Extract plan components from text content using pattern matching
     */
    protected function extractPlanFromText(string $content): array
    {
        $plan = [
            'workflow_name' => 'Generated Workflow',
            'description'   => '',
            'tasks'         => [],
            'connections'   => [],
            'max_workers'   => 5,
        ];

        // Try to extract workflow name
        if (preg_match('/(?:workflow|process)\s*(?:name|title):\s*(.+)$/im', $content, $matches)) {
            $plan['workflow_name'] = trim($matches[1]);
        }

        // Try to extract description
        if (preg_match('/description:\s*(.+?)(?=\n\n|\n[A-Z]|$)/is', $content, $matches)) {
            $plan['description'] = trim($matches[1]);
        }

        // Try to extract tasks using various patterns
        $taskPatterns = [
            '/(?:^|\n)(?:\d+\.|\*|-)\s*([^:\n]+):\s*(.+?)(?=\n(?:\d+\.|\*|-)|$)/ms',
            '/task\s*\d*:\s*(.+?)(?=\ntask|\n\n|$)/ims',
            '/step\s*\d*:\s*(.+?)(?=\nstep|\n\n|$)/ims',
        ];

        foreach ($taskPatterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $taskName        = trim($match[1]);
                    $taskDescription = trim($match[2] ?? '');

                    if (!empty($taskName)) {
                        $plan['tasks'][] = [
                            'name'               => $taskName,
                            'description'        => $taskDescription,
                            'runner_type'        => 'AgentThreadTaskRunner', // Default
                            'agent_requirements' => 'General purpose agent',
                        ];
                    }
                }
                break; // Use first matching pattern
            }
        }

        // If no structured tasks found, create a basic task from the content
        if (empty($plan['tasks'])) {
            $plan['tasks'][] = [
                'name'               => 'Process User Request',
                'description'        => trim(substr($content, 0, 500)), // First 500 chars
                'runner_type'        => 'AgentThreadTaskRunner',
                'agent_requirements' => 'General purpose agent',
            ];
        }

        return $plan;
    }

    /**
     * Validate chat state for workflow building
     */
    protected function validateChatForWorkflowBuild(WorkflowBuilderChat $chat): void
    {
        if ($chat->status !== WorkflowBuilderChat::STATUS_ANALYZING_PLAN) {
            throw new ValidationError('Chat must be in analyzing_plan status to start workflow build', 400);
        }

        $buildState = $chat->getCurrentBuildState();
        if (empty($buildState['generated_plan'] ?? null)) {
            throw new ValidationError('No approved plan found for workflow build', 400);
        }

        // Validate the plan structure and content
        $this->validateWorkflowPlan($buildState['generated_plan']);
    }

    /**
     * Validate workflow plan structure and content before building.
     *
     * Ensures the plan has all required components and valid structure
     * to prevent build failures from malformed plans.
     *
     * @param  array  $plan  The workflow plan to validate
     *
     * @throws ValidationError If plan is invalid or incomplete
     */
    protected function validateWorkflowPlan(array $plan): void
    {
        // Validate essential plan structure
        if (empty($plan['workflow_name'])) {
            throw new ValidationError('Workflow plan is missing a workflow name', 400);
        }

        if (empty($plan['tasks']) || !is_array($plan['tasks'])) {
            throw new ValidationError('Workflow plan must contain at least one task', 400);
        }

        // Validate each task structure
        foreach ($plan['tasks'] as $index => $task) {
            $taskNumber = $index + 1;

            if (empty($task['name'])) {
                throw new ValidationError("Task {$taskNumber} is missing a name", 400);
            }

            if (empty($task['runner_type'])) {
                throw new ValidationError("Task {$taskNumber} ('{$task['name']}') is missing a runner type", 400);
            }

            // Validate runner type is a known type
            $validRunnerTypes = [
                'AgentThreadTaskRunner',
                'CustomTaskRunner',
                'ScriptTaskRunner',
                'HttpTaskRunner',
            ];

            if (!in_array($task['runner_type'], $validRunnerTypes)) {
                throw new ValidationError(
                    "Task {$taskNumber} ('{$task['name']}') has invalid runner type: {$task['runner_type']}. " .
                    'Valid types: ' . implode(', ', $validRunnerTypes),
                    400
                );
            }
        }

        // Validate connections for multi-task workflows
        $taskCount = count($plan['tasks']);
        if ($taskCount > 1) {
            $connections = $plan['connections'] ?? [];

            if (empty($connections)) {
                throw new ValidationError(
                    "Multi-task workflow ({$taskCount} tasks) must define connections between tasks. " .
                    'No connections were specified in the plan.',
                    400
                );
            }

            // Validate connection structure
            foreach ($connections as $index => $connection) {
                $connectionNumber = $index + 1;

                if (empty($connection['source']) || empty($connection['target'])) {
                    throw new ValidationError(
                        "Connection {$connectionNumber} is missing source or target task names",
                        400
                    );
                }

                // Verify source and target tasks exist in plan
                $taskNames = array_column($plan['tasks'], 'name');

                if (!in_array($connection['source'], $taskNames)) {
                    throw new ValidationError(
                        "Connection {$connectionNumber} references unknown source task: {$connection['source']}",
                        400
                    );
                }

                if (!in_array($connection['target'], $taskNames)) {
                    throw new ValidationError(
                        "Connection {$connectionNumber} references unknown target task: {$connection['target']}",
                        400
                    );
                }
            }
        }

        // Validate workflow configuration
        if (isset($plan['max_workers'])) {
            $maxWorkers = (int)$plan['max_workers'];
            if ($maxWorkers < 1 || $maxWorkers > 50) {
                throw new ValidationError(
                    "max_workers must be between 1 and 50, got: {$maxWorkers}",
                    400
                );
            }
        }

        // Log successful validation
        Log::info('Workflow plan validation passed', [
            'workflow_name'    => $plan['workflow_name'],
            'task_count'       => count($plan['tasks']),
            'connection_count' => count($plan['connections'] ?? []),
        ]);
    }

    /**
     * Prepare artifacts for workflow build
     */
    protected function prepareBuildArtifacts(WorkflowBuilderChat $chat): array
    {
        $buildState    = $chat->getCurrentBuildState();
        $workflowInput = $chat->workflowInput;

        // Create comprehensive build artifacts
        $artifacts = [];

        // Main workflow input artifact
        if ($workflowInput) {
            $artifacts[] = $workflowInput->toArtifact();
        }

        // Plan artifact
        if (!empty($buildState['generated_plan'])) {
            $planInput = app(WorkflowInputRepository::class)->createWorkflowInput([
                'name'        => 'Approved Workflow Plan',
                'content'     => json_encode($buildState['generated_plan']),
                'description' => 'User-approved workflow plan for building',
                'team_id'     => $chat->team_id,
            ]);
            $artifacts[] = $planInput->toArtifact();
        }

        // Current workflow state artifact (if modifying existing)
        if ($chat->workflowDefinition) {
            $workflowStateInput = app(WorkflowInputRepository::class)->createWorkflowInput([
                'name'        => 'Current Workflow State',
                'content'     => json_encode($chat->workflowDefinition->toArray()),
                'description' => 'Current workflow definition state for modification',
                'team_id'     => $chat->team_id,
            ]);
            $artifacts[] = $workflowStateInput->toArtifact();
        }

        return $artifacts;
    }

    /**
     * Get the builder workflow definition
     */
    protected function getBuilderWorkflowDefinition(?WorkflowBuilderChat $chat = null): WorkflowDefinition
    {
        // Get team from chat if available, otherwise use team() helper
        if ($chat && $chat->team_id) {
            $teamId = $chat->team_id;
        } else {
            $currentTeam = team();
            if (!$currentTeam) {
                throw new ValidationError('No team context available', 400);
            }
            $teamId = $currentTeam->id;
        }

        $builderWorkflow = WorkflowDefinition::whereNull('team_id')
            ->where('name', 'LLM Workflow Builder')
            ->first();

        if (!$builderWorkflow) {
            throw new ValidationError('LLM Workflow Builder workflow definition not found. Please ensure it is properly configured.', 500);
        }

        return $builderWorkflow;
    }

    /**
     * Validate workflow completion
     */
    protected function validateWorkflowCompletion(WorkflowBuilderChat $chat, WorkflowRun $completedRun): void
    {
        if ($chat->current_workflow_run_id !== $completedRun->id) {
            throw new ValidationError('Completed workflow run does not match chat\'s current workflow run', 400);
        }

        if ($chat->status !== WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW) {
            throw new ValidationError('Chat is not in building_workflow status', 400);
        }
    }

    /**
     * Handle workflow build failure with detailed error capture
     */
    protected function handleWorkflowBuildFailure(WorkflowBuilderChat $chat, WorkflowRun $failedRun): void
    {
        // Collect detailed failure information
        $failureData = $this->extractFailureDetails($failedRun);

        $errorMessage = $this->buildDetailedErrorMessage($failedRun, $failureData);

        $chat->updatePhase(WorkflowBuilderChat::STATUS_FAILED, [
            'build_failed_at' => now()->toISOString(),
            'failure_reason'  => $failedRun->status,
            'workflow_run_id' => $failedRun->id,
            'error'           => $failureData['primary_error'] ?? 'Unknown error',
            'failure_phase'   => $failureData['failed_phase']  ?? 'workflow_build',
            'detailed_errors' => $failureData['all_errors']    ?? [],
        ]);

        $chat->addThreadMessage($errorMessage, [
            'error_type'      => 'workflow_build_failure',
            'workflow_run_id' => $failedRun->id,
            'failure_details' => $failureData,
        ]);

        // Log detailed failure for debugging
        static::logError('Workflow build failure', [
            'chat_id'         => $chat->id,
            'workflow_run_id' => $failedRun->id,
            'status'          => $failedRun->status,
            'failure_data'    => $failureData,
        ]);
    }

    /**
     * Extract detailed failure information from a failed workflow run.
     *
     * @param  WorkflowRun  $failedRun  The failed workflow run
     * @return array Detailed failure information
     */
    protected function extractFailureDetails(WorkflowRun $failedRun): array
    {
        $failureData = [
            'primary_error'     => null,
            'failed_phase'      => 'workflow_build',
            'all_errors'        => [],
            'failed_task_names' => [],
        ];

        // Check for workflow-level errors
        if ($failedRun->error_message) {
            $failureData['primary_error'] = $failedRun->error_message;
            $failureData['all_errors'][]  = [
                'type'    => 'workflow_error',
                'message' => $failedRun->error_message,
            ];
        }

        // Check individual task run failures
        $taskRuns = $failedRun->taskRuns()->where('status', 'failed')->get();
        foreach ($taskRuns as $taskRun) {
            if ($taskRun->error_message) {
                $failureData['all_errors'][] = [
                    'type'      => 'task_error',
                    'task_name' => $taskRun->taskDefinition?->name ?? 'Unknown Task',
                    'message'   => $taskRun->error_message,
                ];
                $failureData['failed_task_names'][] = $taskRun->taskDefinition?->name ?? 'Unknown Task';
            }
        }

        // If no specific error found, use status as fallback
        if (!$failureData['primary_error'] && !empty($failureData['all_errors'])) {
            $failureData['primary_error'] = $failureData['all_errors'][0]['message'];
        } elseif (!$failureData['primary_error']) {
            $failureData['primary_error'] = "Workflow build failed with status: {$failedRun->status}";
        }

        return $failureData;
    }

    /**
     * Build a detailed error message for users.
     *
     * @param  WorkflowRun  $failedRun  The failed workflow run
     * @param  array  $failureData  Extracted failure details
     * @return string User-friendly error message
     */
    protected function buildDetailedErrorMessage(WorkflowRun $failedRun, array $failureData): string
    {
        $message = "Workflow build failed: {$failureData['primary_error']}";

        if (!empty($failureData['failed_task_names'])) {
            $taskNames = implode(', ', array_unique($failureData['failed_task_names']));
            $message .= "\n\nFailed tasks: {$taskNames}";
        }

        if (count($failureData['all_errors']) > 1) {
            $message .= "\n\nAdditional errors:";
            foreach (array_slice($failureData['all_errors'], 1, 3) as $error) {
                $taskInfo = isset($error['task_name']) ? " (Task: {$error['task_name']})" : '';
                $message .= "\n• {$error['message']}{$taskInfo}";
            }

            if (count($failureData['all_errors']) > 4) {
                $remaining = count($failureData['all_errors']) - 4;
                $message .= "\n• ... and {$remaining} more errors";
            }
        }

        return $message;
    }

    /**
     * Extract build artifacts from completed workflow
     */
    protected function extractBuildArtifacts(WorkflowRun $completedRun): array
    {
        $outputArtifacts = $completedRun->collectFinalOutputArtifacts();

        $buildArtifacts = [];
        foreach ($outputArtifacts as $artifact) {
            $buildArtifacts[] = [
                'id'           => $artifact->id,
                'name'         => $artifact->name,
                'content'      => $artifact->json_content ?: $artifact->text_content,
                'type'         => 'unknown', // Artifact model doesn't have type field
                'extracted_at' => now()->toISOString(),
            ];
        }

        return $buildArtifacts;
    }

    /**
     * Apply workflow changes to database models
     */
    protected function applyWorkflowChanges(WorkflowBuilderChat $chat, array $buildArtifacts): WorkflowDefinition
    {
        // Parse build artifacts to extract workflow and task definitions
        $workflowData = $this->parseWorkflowFromArtifacts($buildArtifacts);

        if ($chat->workflowDefinition) {
            // Modifying existing workflow
            $workflowDefinition = $this->updateExistingWorkflow($chat->workflowDefinition, $workflowData);
        } else {
            // Creating new workflow
            $workflowDefinition = $this->createNewWorkflow($workflowData);
        }

        return $workflowDefinition;
    }

    /**
     * Parse workflow data from build artifacts
     */
    protected function parseWorkflowFromArtifacts(array $buildArtifacts): array
    {
        $workflowData = [
            'name'        => 'Generated Workflow',
            'description' => 'Generated via LLM Workflow Builder',
            'max_workers' => 5,
            'tasks'       => [],
            'connections' => [],
        ];

        foreach ($buildArtifacts as $artifact) {
            $content = $artifact['content'] ?? null;

            if (is_string($content)) {
                // Try to decode JSON content
                $jsonContent = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $jsonContent;
                }
            }

            if (is_array($content)) {
                // Extract workflow definition data
                if (isset($content['action']) && $content['action'] === 'create' && isset($content['task_definition'])) {
                    // This is a task definition artifact
                    $workflowData['tasks'][] = $content['task_definition'];
                } elseif (isset($content['workflow_definition'])) {
                    // This is a workflow organization artifact
                    $workflowDef                 = $content['workflow_definition'];
                    $workflowData['name']        = $workflowDef['name']        ?? $workflowData['name'];
                    $workflowData['description'] = $workflowDef['description'] ?? $workflowData['description'];
                    $workflowData['max_workers'] = $workflowDef['max_workers'] ?? $workflowData['max_workers'];

                    if (isset($content['task_specifications'])) {
                        foreach ($content['task_specifications'] as $taskSpec) {
                            $workflowData['tasks'][] = $this->normalizeTaskSpecification($taskSpec);
                        }
                    }

                    if (isset($content['connections'])) {
                        $workflowData['connections'] = array_merge($workflowData['connections'], $content['connections']);
                    }
                }
            }
        }

        return $workflowData;
    }

    /**
     * Normalize task specification to TaskDefinition format
     */
    protected function normalizeTaskSpecification(array $taskSpec): array
    {
        return [
            'name'                  => $taskSpec['name']                  ?? 'Unnamed Task',
            'description'           => $taskSpec['description']           ?? '',
            'prompt'                => $taskSpec['prompt']                ?? null,
            'task_runner_name'      => $taskSpec['runner_type']           ?? $taskSpec['task_runner_name'] ?? 'AgentThreadTaskRunner',
            'task_runner_config'    => $taskSpec['configuration']         ?? null,
            'timeout_after_seconds' => $taskSpec['timeout_after_seconds'] ?? 300,
            'input_artifact_mode'   => $taskSpec['input_artifact_mode']   ?? null,
            'output_artifact_mode'  => $taskSpec['output_artifact_mode']  ?? null,
            'agent_requirements'    => $taskSpec['agent_requirements']    ?? null,
        ];
    }

    /**
     * Create new workflow from parsed data
     */
    protected function createNewWorkflow(array $workflowData): WorkflowDefinition
    {
        $workflowDefinition = WorkflowDefinition::create([
            'name'        => $workflowData['name'],
            'description' => $workflowData['description'],
            'max_workers' => $workflowData['max_workers'],
            'team_id'     => team()->id,
        ]);

        // Debug: Check tasks before passing to createWorkflowTasks
        if (empty($workflowData['tasks'])) {
            throw new Exception('createNewWorkflow: tasks array is empty in workflowData. Keys: ' . implode(', ', array_keys($workflowData)));
        }

        $this->createWorkflowTasks($workflowDefinition, $workflowData['tasks']);
        $this->createWorkflowConnections($workflowDefinition, $workflowData['connections']);

        return $workflowDefinition;
    }

    /**
     * Update existing workflow with parsed data
     */
    protected function updateExistingWorkflow(WorkflowDefinition $workflowDefinition, array $workflowData): WorkflowDefinition
    {
        $workflowDefinition->update([
            'description' => $workflowData['description'],
            'max_workers' => $workflowData['max_workers'],
        ]);

        // For simplicity, we'll add new tasks rather than trying to merge
        // In a full implementation, you might want to analyze differences and update accordingly
        $this->createWorkflowTasks($workflowDefinition, $workflowData['tasks']);
        $this->createWorkflowConnections($workflowDefinition, $workflowData['connections']);

        return $workflowDefinition;
    }

    /**
     * Create workflow tasks and nodes
     */
    protected function createWorkflowTasks(WorkflowDefinition $workflowDefinition, array $tasks): void
    {
        if (empty($tasks)) {
            throw new Exception('createWorkflowTasks called with empty tasks array');
        }

        foreach ($tasks as $index => $taskData) {
            // Find or create agent if specified
            $agent = null;
            if (!empty($taskData['agent_requirements'])) {
                $agent = $this->findSuitableAgent($taskData['agent_requirements']);
            }

            // Create task definition
            $mergedData = array_merge($taskData, [
                'team_id'  => team()->id,
                'agent_id' => $agent?->id,
            ]);

            try {
                $taskDefinition = TaskDefinition::create($mergedData);
            } catch (Exception $e) {
                throw new Exception('Failed to create TaskDefinition: ' . $e->getMessage() . '. Data: ' . json_encode($mergedData));
            }

            // Create workflow node
            $workflowNode = new WorkflowNode([
                'task_definition_id' => $taskDefinition->id,
                'name'               => $taskDefinition->name,
            ]);
            $workflowNode->workflow_definition_id = $workflowDefinition->id;
            $workflowNode->save();
        }
    }

    /**
     * Create workflow connections
     */
    protected function createWorkflowConnections(WorkflowDefinition $workflowDefinition, array $connections): void
    {
        $nodes = $workflowDefinition->workflowNodes()->with('taskDefinition')->get();

        foreach ($connections as $connection) {
            $sourceName = $connection['source'] ?? null;
            $targetName = $connection['target'] ?? null;

            if ($sourceName && $targetName) {
                $sourceNode = $nodes->first(function ($node) use ($sourceName) {
                    return $node->taskDefinition && $node->taskDefinition->name === $sourceName;
                });

                $targetNode = $nodes->first(function ($node) use ($targetName) {
                    return $node->taskDefinition && $node->taskDefinition->name === $targetName;
                });

                if ($sourceNode && $targetNode) {
                    $workflowConnection = new WorkflowConnection([
                        'source_node_id' => $sourceNode->id,
                        'target_node_id' => $targetNode->id,
                        'name'           => $connection['name'] ?? "{$sourceName} to {$targetName}",
                    ]);
                    $workflowConnection->workflow_definition_id = $workflowDefinition->id;
                    $workflowConnection->save();
                }
            }
        }
    }

    /**
     * Find suitable agent based on requirements
     */
    protected function findSuitableAgent(string $requirements): ?Agent
    {
        // Simple matching logic - in a full implementation, you might use embeddings or more sophisticated matching
        $lowercaseReq = strtolower($requirements);

        // Try to find specific agents first
        if (str_contains($lowercaseReq, 'planner') || str_contains($lowercaseReq, 'planning')) {
            $agent = Agent::whereNull('team_id')->where('name', 'Workflow Planner')->first();
            if ($agent) {
                return $agent;
            }
        }

        if (str_contains($lowercaseReq, 'evaluator') || str_contains($lowercaseReq, 'evaluation')) {
            $agent = Agent::whereNull('team_id')->where('name', 'Workflow Evaluator')->first();
            if ($agent) {
                return $agent;
            }
        }

        // Fallback to first available agent
        return Agent::where('team_id', team()->id)->first();
    }

    /**
     * Validate chat state for evaluation
     */
    protected function validateChatForEvaluation(WorkflowBuilderChat $chat): void
    {
        if ($chat->status !== WorkflowBuilderChat::STATUS_EVALUATING_RESULTS) {
            throw new ValidationError('Chat is not in evaluating_results status', 400);
        }

        if (empty($chat->getLatestArtifacts())) {
            throw new ValidationError('No build artifacts found for evaluation', 400);
        }
    }

    /**
     * Create agent thread for result evaluation
     */
    protected function createEvaluationAgentThread(WorkflowBuilderChat $chat): AgentThread
    {
        $evaluationAgent = $this->getEvaluationAgent();

        return AgentThread::create([
            'name'     => 'Workflow Evaluation: ' . $chat->id,
            'agent_id' => $evaluationAgent->id,
            'team_id'  => team()->id,
        ]);
    }

    /**
     * Get the evaluation agent
     */
    protected function getEvaluationAgent(): Agent
    {
        $agent = Agent::whereNull('team_id')
            ->where('name', 'Workflow Evaluator')
            ->first();

        if (!$agent) {
            throw new ValidationError('Workflow Evaluator agent not found. Please ensure the agent is configured.', 500);
        }

        return $agent;
    }

    /**
     * Generate result summary from evaluation
     */
    protected function generateResultSummary(WorkflowBuilderChat $chat, $evaluationMessage): array
    {
        $content = $evaluationMessage->content ?? 'Workflow build completed successfully.';

        return [
            'message' => $content,
            'data'    => [
                'evaluation_completed_at' => now()->toISOString(),
                'workflow_definition_id'  => $chat->workflow_definition_id,
                'artifacts_count'         => count($chat->getLatestArtifacts()),
            ],
        ];
    }
}
