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
        $assignment = $workflowTask->workflowAssignment;

        $data    = null;
        $content = null;

        // If the agent responded with a message, set the content or data
        // NOTE: Sometimes an agent may respond with a tools response that includes an is_finished flag, and our lastMessage may be empty
        if ($threadRun->lastMessage) {
            if ($assignment->agent->response_format !== 'text') {
                $data = $threadRun->lastMessage->getJsonContent();
            } else {
                $content = $threadRun->lastMessage->getCleanContent();
            }
        }

        $artifact = $workflowTask->artifacts()->create([
            'name'    => $workflowTask->thread->name,
            'model'   => $assignment->agent->model,
            'content' => $content,
            'data'    => $data,
        ]);

        Log::debug("$workflowTask created $artifact");
    }
}
