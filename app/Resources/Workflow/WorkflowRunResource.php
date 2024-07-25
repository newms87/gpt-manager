<?php

namespace App\Resources\Workflow;

use App\Models\Agent\Message;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Resources\Agent\MessageResource;
use App\Resources\Agent\ThreadResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class WorkflowRunResource extends ActionResource
{
    /**
     * @param WorkflowRun $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'                => $model->id,
            'workflow_id'       => $model->workflow_id,
            'workflow_run_name' => $model->workflow?->name . ' (' . $model->id . ')',
            'input_name'        => $model->workflowInput?->name,
            'status'            => $model->status,
            'started_at'        => $model->started_at,
            'completed_at'      => $model->completed_at,
            'failed_at'         => $model->failed_at,
            'created_at'        => $model->created_at,
            'usage'             => [
                'input_tokens'  => $model->getTotalInputTokens(),
                'output_tokens' => $model->getTotalOutputTokens(),
                'cost'          => $model->getTotalCost(),
            ],
        ];
    }

    /**
     * @param WorkflowRun $model
     */
    public static function details(Model $model): array
    {
        $jobRuns = $model->sortedWorkflowJobRuns()->with(['workflowJob', 'tasks.jobDispatch.runningAuditRequest', 'tasks.thread.messages.storedFiles.transcodes'])->get();

        return static::make($model, [
            'workflowInput'   => WorkflowInputResource::make($model->workflowInput),
            'artifacts'       => ArtifactResource::collection($model->artifacts, fn(Artifact $artifact) => [
                'content' => $artifact->content,
                'data'    => $artifact->data,
            ]),
            'workflowJobRuns' => WorkflowJobRunResource::collection($jobRuns, fn(WorkflowJobRun $workflowJobRun) => [
                'depth'       => $workflowJobRun->workflowJob?->dependency_level,
                'workflowJob' => WorkflowJobResource::make($workflowJobRun->workflowJob),
                'tasks'       => WorkflowTaskResource::collection($workflowJobRun->tasks, fn(WorkflowTask $task) => [
                    'audit_request_id' => $task->jobDispatch?->runningAuditRequest?->id,
                    'logs'             => $task->jobDispatch?->runningAuditRequest?->logs,
                    'thread'           => ThreadResource::make($task->thread, [
                        'messages' => MessageResource::collection($task->thread?->messages, fn(Message $message) => [
                            'files' => StoredFileResource::collection($message->storedFiles, fn(StoredFile $file) => [
                                'transcodes' => StoredFileResource::collection($file->transcodes),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }
}
