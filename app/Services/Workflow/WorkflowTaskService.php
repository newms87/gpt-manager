<?php

namespace App\Services\Workflow;

use App\Models\Workflow\WorkflowTask;
use App\Repositories\MessageRepository;
use App\Repositories\ThreadRepository;
use Exception;
use Flytedan\DanxLaravel\Jobs\Job;
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
        $workflowTask->started_at = now();
        $workflowTask->jobDispatch()->associate(Job::$runningJob);
        $workflowTask->save();

        $inputSource = $workflowTask->workflowRun->inputSource;
        $assignment  = $workflowTask->workflowAssignment;

        try {
            // Create a thread w/ a message containing the Input Source content and files
            $thread  = app(ThreadRepository::class)->create($assignment->agent, "Workflow Task: {$workflowTask->id}");
            $message = app(MessageRepository::class)->create($thread, MessageRepository::$model::ROLE_USER, [
                'content' => $inputSource->content,
            ]);
            app(MessageRepository::class)->saveFiles($message, $inputSource->storedFiles->pluck('id')->toArray());

            // Run the thread
            $threadRun = app(ThreadRepository::class)->run($thread);

            // Produce the artifact
            $lastMessage = $threadRun->lastMessage;
            $workflowTask->artifact()->create([
                'group'   => $assignment->group,
                'name'    => "Workflow Task: {$workflowTask->id}",
                'model'   => $assignment->agent->model,
                'content' => $lastMessage->content,
                'data'    => $lastMessage->data,
            ]);

            $workflowTask->completed_at = now();
            $workflowTask->save();
        } catch(Exception $e) {
            $workflowTask->failed_at = now();
            $workflowTask->save();
        }

        // Notify the Workflow our task is finished
        WorkflowService::taskFinished($workflowTask);
    }
}
