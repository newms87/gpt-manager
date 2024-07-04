<?php

namespace App\WorkflowTools;

use App\Models\Agent\Thread;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;

class RunAgentThreadWorkflowTool extends WorkflowTool
{
    public static string $toolName = 'Run Agent Thread';

    /**
     * @param WorkflowTask $workflowTask
     * @return void
     * @throws ValidationError
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        $thread = $this->setupTaskThread($workflowTask);

        // Run the thread synchronously
        $threadRun = app(AgentThreadService::class)->run($thread, dispatch: false);

        // Produce the artifact
        $lastMessage = $threadRun->lastMessage;
        $assignment  = $workflowTask->workflowAssignment;

        $content = AgentThreadService::cleanContent($lastMessage->content);

        $artifact = $workflowTask->artifact()->create([
            'name'    => $thread->name,
            'model'   => $assignment->agent->model,
            'content' => $content,
            'data'    => $lastMessage->data,
        ]);

        Log::debug("$workflowTask created $artifact");
    }

    /**
     * Create a thread for the task and add messages from the workflow input or dependencies
     *
     * @param WorkflowTask $workflowTask
     * @return Thread
     */
    protected function setupTaskThread(WorkflowTask $workflowTask): Thread
    {
        $workflowJobRun = $workflowTask->workflowJobRun;
        $workflowRun    = $workflowJobRun->workflowRun;
        $assignment     = $workflowTask->workflowAssignment;
        $workflowJob    = $workflowTask->workflowJob;

        $threadName = $workflowTask->workflowJob->name . " ($workflowTask->id) [group: " . ($workflowTask->group ?: 'default') . "] by {$assignment->agent->name}";
        $thread     = app(ThreadRepository::class)->create($assignment->agent, $threadName);

        Log::debug("$workflowTask created $thread");

        if ($workflowJob->use_input) {
            // If we have no dependencies, then we want to use the workflow input
            $this->addThreadMessageFromWorkflowInput($thread, $workflowRun->workflowInput);
        }

        // If we have dependencies, we want to use the artifacts from the dependencies
        if ($workflowJob->dependencies->isNotEmpty()) {
            foreach($workflowJob->dependencies as $dependency) {
                $dependsOnWorkflowJobRun = $workflowRun->workflowJobRuns->where('workflow_job_id', $dependency->depends_on_workflow_job_id)->first();
                $this->addThreadMessagesFromArtifacts($thread, $dependsOnWorkflowJobRun->artifacts, $dependency->group_by, $workflowTask->group);
            }
        }

        $workflowTask->thread()->associate($thread)->save();

        return $thread;
    }

    /**
     * Add messages from artifacts to a thread
     *
     * @param Thread                $thread
     * @param Artifact[]|Collection $artifacts
     * @param                       $groupBy
     * @param                       $group
     * @return void
     */
    public function addThreadMessagesFromArtifacts(Thread $thread, $artifacts, $groupBy, $group): void
    {
        foreach($artifacts as $artifact) {
            if ($groupBy) {
                $groupedContent = $artifact->groupContentBy($groupBy);
                if (!$groupedContent) {
                    Log::debug("$thread skipped $artifact: missing group by field $groupBy");
                    continue;
                }
                $content = $groupedContent[$group] ?? null;
                if (!$content) {
                    Log::debug("$thread skipped $artifact: grouped content does not have any content in group $group");
                    continue;
                }
                app(ThreadRepository::class)->addMessageToThread($thread, $content);
                Log::debug("$thread added message for group $group from $artifact (used group by $groupBy)");
            } else {
                app(ThreadRepository::class)->addMessageToThread($thread, $artifact->content);
                Log::debug("$thread added message for $artifact");
            }
        }
    }

    /**
     * Create a message and append it to the thread based on the Workflow Input content + files
     *
     * @param Thread        $thread
     * @param WorkflowInput $workflowInput
     * @return void
     */
    public function addThreadMessageFromWorkflowInput(Thread $thread, WorkflowInput $workflowInput): void
    {
        $content = $workflowInput->content;
        $fileIds = $workflowInput->storedFiles->pluck('id')->toArray();
        app(ThreadRepository::class)->addMessageToThread($thread, $content, $fileIds);
        Log::debug("$thread added $workflowInput");
    }
}
