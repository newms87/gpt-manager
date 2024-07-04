<?php

namespace App\Resources\Workflow;

use App\Models\Agent\Message;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Resources\Agent\MessageResource;
use App\Resources\Agent\ThreadResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class WorkflowInputResource extends ActionResource
{
    /**
     * @param WorkflowInput $model
     */
    public static function data(Model $model): array
    {
        $thumbFile = $model->storedFiles()->first();

        return [
            'id'                      => $model->id,
            'name'                    => $model->name,
            'description'             => $model->description,
            'workflow_runs_count'     => $model->workflow_runs_count,
            'thumb'                   => StoredFileResource::make($thumbFile),
            'has_active_workflow_run' => $model->activeWorkflowRuns()->exists(),
            'tags'                    => $model->objectTags()->pluck('name'),
            'created_at'              => $model->created_at,
            'updated_at'              => $model->updated_at,
        ];
    }

    /**
     * @param WorkflowInput $model
     */
    public static function details(Model $model): array
    {
        $storedFiles  = $model->storedFiles()->with('transcodes')->get();
        $workflowRuns = $model->workflowRuns()->with(['artifacts', 'workflowJobRuns' => ['workflowJob', 'tasks' => ['jobDispatch.runningAuditRequest', 'thread.messages.storedFiles.transcodes']]])->orderByDesc('id')->get();

        return static::make($model, [
            'files'        => StoredFileResource::collection($storedFiles, fn(StoredFile $storedFile) => [
                'transcodes' => StoredFileResource::collection($storedFile->transcodes),
            ]),
            'content'      => $model->content,

            // TODO: Refactor this to query only a single Workflow Run when needed (see WorkflowResource)
            'workflowRuns' => WorkflowRunResource::collection($workflowRuns, fn(WorkflowRun $workflowRun) => [
                'artifacts'       => ArtifactResource::collection($workflowRun->artifacts, fn(Artifact $artifact) => [
                    'content' => $artifact->content,
                    'data'    => $artifact->data,
                ]),
                'workflowJobRuns' => WorkflowJobRunResource::collection($workflowRun->workflowJobRuns, fn(WorkflowJobRun $workflowJobRun) => [
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
            ]),
        ]);
    }
}
