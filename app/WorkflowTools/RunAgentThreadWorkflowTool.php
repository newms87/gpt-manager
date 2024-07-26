<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowTask;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\StringHelper;

class RunAgentThreadWorkflowTool extends WorkflowTool
{
    use AssignsWorkflowTasksTrait, ResolvesDependencyArtifactsTrait;

    public static string $toolName = 'Run Agent Thread';

    public function getResponsePreview(WorkflowJob $workflowJob): array|string|null
    {
        $response = [];
        foreach($workflowJob->workflowAssignments as $assignment) {
            $response[] = $assignment->agent->response_sample;
        }

        return $response;
    }

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
        $data    = null;

        if ($assignment->agent->response_format !== 'text') {
            $data    = StringHelper::safeJsonDecode($content, 1000000);
            $content = null;
        }

        $artifact = $workflowTask->artifact()->create([
            'name'    => $workflowTask->thread->name,
            'model'   => $assignment->agent->model,
            'content' => $content,
            'data'    => $data,
        ]);

        Log::debug("$workflowTask created $artifact");
    }
}
