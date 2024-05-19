<?php

namespace App\Services\Workflow;

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
                'group'   => $assignment->group,
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

        $threadName = $workflowTask->workflowJob->name . ": {$assignment->agent->name} ($workflowTask->id)";
        $thread     = app(ThreadRepository::class)->create($assignment->agent, $threadName);

        Log::debug("$workflowTask created $thread");

        // If we have dependencies, we want to use the artifacts from the dependencies
        if ($workflowJob->depends_on) {
            foreach($workflowJob->depends_on as $workflowJobId) {
                $dependsOnWorkflowJobRun = $workflowRun->workflowJobRuns->where('workflow_job_id', $workflowJobId)->first();

                foreach($dependsOnWorkflowJobRun->artifacts as $artifact) {
                    Log::debug("$thread using $artifact from $dependsOnWorkflowJobRun");
                    app(ThreadRepository::class)->addMessageToThread($thread, $artifact->content);
                }
            }
        } else {
            // If we have no dependencies, then we want to use the input source
            $inputSource = $workflowJobRun->workflowRun->inputSource;
            $content     = $inputSource->content;
            $fileIds     = $inputSource->storedFiles->pluck('id')->toArray();
            app(ThreadRepository::class)->addMessageToThread($thread, $content, $fileIds);
        }

        $workflowTask->thread()->associate($thread)->save();

        return $thread;
    }
}
