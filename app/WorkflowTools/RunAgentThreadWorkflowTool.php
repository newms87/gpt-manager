<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowTask;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Log;

class RunAgentThreadWorkflowTool extends WorkflowTool
{
    use AssignsWorkflowTasksTrait, ResolvesDependencyArtifactsTrait;
    
    public static string $toolName = 'Run Agent Thread';

    /**
     * Run the thread associated to the task and produce an artifact from the last message
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        // Run the thread synchronously
        $threadRun = app(AgentThreadService::class)->run($workflowTask->thread, dispatch: false);

        // Produce the artifact
        $lastMessage = $threadRun->lastMessage;
        $assignment  = $workflowTask->workflowAssignment;

        $content = AgentThreadService::cleanContent($lastMessage->content);

        $artifact = $workflowTask->artifact()->create([
            'name'    => $workflowTask->thread->name,
            'model'   => $assignment->agent->model,
            'content' => $content,
            'data'    => $lastMessage->data,
        ]);

        Log::debug("$workflowTask created $artifact");
    }
}
