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
            'id'              => $model->id,
            'workflow_id'     => $model->workflow_id,
            'workflow_name'   => $model->workflow?->name,
            'input_id'        => $model->workflowInput?->id,
            'input_name'      => $model->workflowInput?->name,
            'status'          => $model->status,
            'job_runs_count'  => $model->job_runs_count,
            'artifacts_count' => $model->artifacts_count,
            'started_at'      => $model->started_at,
            'completed_at'    => $model->completed_at,
            'failed_at'       => $model->failed_at,
            'created_at'      => $model->created_at,
            'usage'           => [
                'input_tokens'  => $model->getTotalInputTokens(),
                'output_tokens' => $model->getTotalOutputTokens(),
                'total_cost'    => $model->getTotalCost(),
            ],
        ];
    }

    /**
     * @param WorkflowRun $model
     */
    public static function details(Model $model): array
    {
        return static::make($model, [
            'workflowInput' => WorkflowInputResource::make($model->workflowInput),
            ...static::artifacts($model),
            ...static::workflowJobRuns($model),
        ]);
    }

    public static function artifacts(WorkflowRun $workflowRun): array
    {
        return [
            'artifacts' => ArtifactResource::collection($workflowRun->artifacts, fn(Artifact $artifact) => [
                'content' => $artifact->content,
                'data'    => $artifact->data,
            ]),
        ];
    }

    public static function workflowJobRuns(WorkflowRun $workflowRun): array
    {
        $jobRuns = $workflowRun->sortedWorkflowJobRuns()->with(['workflowJob', 'tasks.jobDispatch.runningAuditRequest', 'tasks.thread.messages.storedFiles.transcodes'])->get();

        return [
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
        ];
    }
}
