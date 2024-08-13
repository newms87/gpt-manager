<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowTask;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Log;

class RunAgentThreadWorkflowTool extends WorkflowTool
{
    use AssignsWorkflowTasksTrait, ResolvesDependencyArtifactsTrait;

    public static string $toolName = 'Run Agent Thread';

    /**
     * Get a list of response formats from all agents or the response schema itself (as a list of 1 item)
     */
    public function getResponsesPreview(WorkflowJob $workflowJob): array
    {
        if ($workflowJob->response_schema) {
            return [$workflowJob->response_schema];
        }

        $responses = [];
        foreach($workflowJob->workflowAssignments as $assignment) {
            if ($assignment->agent->response_format === 'text') {
                $responses[] = ['content' => 'Text content'];
            } else {
                $responses[] = $assignment->agent->response_sample;
            }
        }

        return $responses;
    }

    /**
     * Run the thread associated to the task and produce an artifact from the last message
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        // Run the thread synchronously
        $threadRun = app(AgentThreadService::class)->run($workflowTask->thread, dispatch: false);

        // Produce the artifact
        $assignment = $workflowTask->workflowAssignment;

        if ($assignment->agent->response_format !== 'text') {
            $data    = $threadRun->lastMessage->getJsonContent();
            $content = null;
        } else {
            $data    = null;
            $content = $threadRun->lastMessage->getCleanContent();
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
