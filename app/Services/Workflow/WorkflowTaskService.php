<?php

namespace App\Services\Workflow;

use App\Models\Workflow\WorkflowTask;
use App\Repositories\MessageRepository;
use App\Repositories\ThreadRepository;
use Exception;
use Flytedan\DanxLaravel\Jobs\Job;
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
        $workflowTask->jobDispatch()->associate(Job::$runningJob);
        $workflowTask->save();

        $inputSource = $workflowTask->workflowJobRun->workflowRun->inputSource;
        $assignment  = $workflowTask->workflowAssignment;

        try {
            // Create a thread w/ a message containing the Input Source content and files
            $thread  = app(ThreadRepository::class)->create($assignment->agent, "Workflow Task: {$workflowTask->id}");
            $message = app(MessageRepository::class)->create($thread, MessageRepository::$model::ROLE_USER, [
                'content' => $inputSource->content,
            ]);
            app(MessageRepository::class)->saveFiles($message, $inputSource->storedFiles->pluck('id')->toArray());
            $workflowTask->thread()->associate($thread)->save();

            Log::debug("$thread created for $workflowTask");

            // Run the thread
            $threadRun = app(ThreadRepository::class)->run($thread);

            // Produce the artifact
            $lastMessage = $threadRun->lastMessage;
            $artifact    = $workflowTask->artifact()->create([
                'group'   => $assignment->group,
                'name'    => $workflowTask->workflowJob->name . ": {$assignment->agent->name} ($workflowTask->id)",
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
}
