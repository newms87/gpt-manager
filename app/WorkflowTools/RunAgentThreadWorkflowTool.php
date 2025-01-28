<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowTask;
use App\Services\AgentThread\AgentThreadMessageToArtifactMapper;
use App\Services\AgentThread\AgentThreadService;
use App\WorkflowTools\Traits\AssignsWorkflowTasksTrait;
use App\WorkflowTools\Traits\ResolvesDependencyArtifactsTrait;
use Exception;

class RunAgentThreadWorkflowTool extends WorkflowTool
{
    use AssignsWorkflowTasksTrait, ResolvesDependencyArtifactsTrait;

    public static string $toolName = 'Run Agent AgentThread';

    /**
     * Run the thread associated to the task and produce an artifact from the last message
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        if (!$workflowTask->thread) {
            throw new Exception("$workflowTask does not have a thread to run");
        }

        // Produce the artifact
        $assignment = $workflowTask->workflowAssignment;

        if (!$assignment) {
            throw new Exception("$workflowTask does not have a workflow assignment");
        }

        // Run the thread synchronously
        $threadRun = app(AgentThreadService::class)->run($workflowTask->thread, dispatch: false);

        // Create the artifact
        if ($threadRun->lastMessage) {
            $artifact = (new AgentThreadMessageToArtifactMapper)->setMessage($threadRun->lastMessage)->map();

            if ($artifact) {
                $workflowTask->artifacts()->attach($artifact);
            }
        }
    }
}
