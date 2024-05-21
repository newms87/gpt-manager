<?php

namespace App\WorkflowTools;

use App\Models\Agent\Thread;
use App\Models\Shared\Artifact;
use App\Models\Shared\InputSource;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\ThreadRepository;
use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RunAgentThreadWorkflowTool extends WorkflowTool
{
    /**
     * @param WorkflowTask $workflowTask
     * @return void
     * @throws ValidationError
     */
    public function runTask(WorkflowTask $workflowTask): void
    {
        $thread = $this->setupTaskThread($workflowTask);

        // Run the thread
        $threadRun = app(ThreadRepository::class)->run($thread);

        // Produce the artifact
        $lastMessage = $threadRun->lastMessage;
        $assignment  = $workflowTask->workflowAssignment;

        $content = $this->cleanContent($lastMessage->content);

        $artifact = $workflowTask->artifact()->create([
            'name'    => $thread->name,
            'model'   => $assignment->agent->model,
            'content' => $content,
            'data'    => $lastMessage->data,
        ]);

        Log::debug("$workflowTask created $artifact");
    }

    /**
     * Create a thread for the task and add messages from the input source or dependencies
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

        if ($workflowJob->use_input_source) {
            // If we have no dependencies, then we want to use the input source
            $this->addThreadMessageFromInputSource($thread, $workflowRun->inputSource);
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
                    Log::debug("$thread skipped $artifact: grouped content does not have group $group");
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
     * Create a message and append it to the thread based on the Input Source content + files
     *
     * @param Thread      $thread
     * @param InputSource $inputSource
     * @return void
     */
    public function addThreadMessageFromInputSource(Thread $thread, InputSource $inputSource): void
    {
        $content = $inputSource->content;
        $fileIds = $inputSource->storedFiles->pluck('id')->toArray();
        app(ThreadRepository::class)->addMessageToThread($thread, $content, $fileIds);
        Log::debug("$thread added $inputSource");
    }

    /**
     * Cleans the AI Model responses to make sure we have valid JSON, if the response is JSON
     * @param $content
     * @return string
     */
    public function cleanContent($content): string
    {
        // Remove any ```json and trailing ``` from content if they are present
        return preg_replace('/^```json\n(.*)\n```$/s', '$1', trim($content));
    }
}
