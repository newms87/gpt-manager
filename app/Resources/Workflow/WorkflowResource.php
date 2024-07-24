<?php

namespace App\Resources\Workflow;

use App\Models\Agent\Message;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Resources\Agent\AgentResource;
use App\Resources\Agent\MessageResource;
use App\Resources\Agent\ThreadResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class WorkflowResource extends ActionResource
{
    /**
     * @param Workflow $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'          => $model->id,
            'name'        => $model->name,
            'description' => $model->description,
            'runs_count'  => $model->runs_count,
            'jobs_count'  => $model->jobs_count,
            'created_at'  => $model->created_at,
        ];
    }

    /**
     * @param Workflow $model
     */
    public static function details(Model $model): array
    {
        $jobs = $model->sortedAgentWorkflowJobs()->with(['dependencies', 'workflowAssignments.agent'])->get();
        $runs = $model->workflowRuns()->with(['artifacts', 'sortedWorkflowJobRuns' => ['workflowJob', 'tasks' => ['jobDispatch.runningAuditRequest', 'thread.messages.storedFiles.transcodes']]])->orderByDesc('id')->get();

        return static::make($model, [
            'jobs' => WorkflowJobResource::collection($jobs, fn(WorkflowJob $workflowJob) => [
                'tasks_preview' => $workflowJob->getTasksPreview(),
                'dependencies'  => WorkflowJobDependencyResource::collection($workflowJob->dependencies),
                'assignments'   => WorkflowAssignmentResource::collection($workflowJob->workflowAssignments, fn(WorkflowAssignment $workflowAssignment) => [
                    'agent' => AgentResource::make($workflowAssignment->agent),
                ]),
            ]),
            
            // TODO: Refactor this to query only a single Workflow Run when needed (see WorkflowInputResource)
            'runs' => WorkflowRunResource::collection($runs, fn(WorkflowRun $workflowRun) => [
                'artifacts'       => ArtifactResource::collection($workflowRun->artifacts, fn(Artifact $artifact) => [
                    'content' => $artifact->content,
                    'data'    => $artifact->data,
                ]),
                'workflowJobRuns' => WorkflowJobRunResource::collection($workflowRun->sortedWorkflowJobRuns, fn(WorkflowJobRun $workflowJobRun) => [
                    'depth'       => $workflowJobRun->workflowJob->dependency_level,
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
