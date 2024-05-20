<?php

namespace App\Services\Workflow;

use App\Models\Agent\Thread;
use App\Models\Shared\Artifact;
use App\Models\Shared\InputSource;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\ThreadRepository;
use Exception;
use Flytedan\DanxLaravel\Models\Audit\ErrorLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowTaskService
{
    /**
     * Run a Workflow Task, produce an artifact and notify the Workflow of completion / failure
     *
     * @param WorkflowTask $workflowTask
     * @return void
     * @throws Throwable
     */
    public static function start(WorkflowTask $workflowTask): void
    {
        Log::debug("$workflowTask started");
        $workflowTask->started_at = now();
        $workflowTask->save();

        try {
            $thread = WorkflowTaskService::setupTaskThread($workflowTask);

            // Run the thread
            $threadRun = app(ThreadRepository::class)->run($thread);

            // Produce the artifact
            $lastMessage = $threadRun->lastMessage;
            $assignment  = $workflowTask->workflowAssignment;
            $artifact    = $workflowTask->artifact()->create([
                'name'    => $thread->name,
                'model'   => $assignment->agent->model,
                'content' => $lastMessage->content,
                'data'    => $lastMessage->data,
            ]);

            $workflowTask->completed_at = now();
            $workflowTask->save();

            Log::debug("$workflowTask created $artifact");
        } catch(Exception $e) {
            ErrorLog::logException(ErrorLog::ERROR, $e);
            $workflowTask->failed_at = now();
            $workflowTask->save();
        }

        // Notify the Workflow our task is finished
        WorkflowService::taskFinished($workflowTask);
    }

    public static function setupTaskThread(WorkflowTask $workflowTask)
    {
        $workflowJobRun = $workflowTask->workflowJobRun;
        $workflowRun    = $workflowJobRun->workflowRun;
        $assignment     = $workflowTask->workflowAssignment;
        $workflowJob    = $workflowTask->workflowJob;

        $threadName = $workflowTask->workflowJob->name . " Group " . ($workflowTask->group ?: '(default)') . ": {$assignment->agent->name} ($workflowTask->id)";
        $thread     = app(ThreadRepository::class)->create($assignment->agent, $threadName);

        Log::debug("$workflowTask created $thread");

        if ($workflowJob->use_input_source) {
            // If we have no dependencies, then we want to use the input source
            WorkflowTaskService::addThreadMessageFromInputSource($thread, $workflowRun->inputSource);
        }

        // If we have dependencies, we want to use the artifacts from the dependencies
        if ($workflowJob->dependencies->isNotEmpty()) {
            foreach($workflowJob->dependencies as $dependency) {
                $dependsOnWorkflowJobRun = $workflowRun->workflowJobRuns->where('workflow_job_id', $dependency->depends_on_workflow_job_id)->first();

                WorkflowTaskService::addThreadMessagesFromArtifacts($thread, $dependsOnWorkflowJobRun->artifacts, $dependency->group_by, $workflowTask->group);
            }
        }

        $workflowTask->thread()->associate($thread)->save();

        return $thread;
    }

    /**
     * Add messages from artifacts to a thread
     *
     * @param Thread     $thread
     * @param Artifact[] $artifacts
     * @param            $groupBy
     * @param            $group
     * @return void
     */
    public static function addThreadMessagesFromArtifacts(Thread $thread, $artifacts, $groupBy, $group): void
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

    public static function addThreadMessageFromInputSource(Thread $thread, InputSource $inputSource): void
    {
        $content = $inputSource->content;
        $fileIds = $inputSource->storedFiles->pluck('id')->toArray();
        app(ThreadRepository::class)->addMessageToThread($thread, $content, $fileIds);
        Log::debug("$thread added $inputSource");
    }
}
