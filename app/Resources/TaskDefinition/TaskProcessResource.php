<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskProcess;
use App\Resources\Agent\AgentThreadResource;
use App\Resources\Usage\UsageSummaryResource;
use App\Resources\Workflow\ArtifactResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\Job\JobDispatchResource;

class TaskProcessResource extends ActionResource
{
    public static function data(TaskProcess $taskProcess, array $includedFields = []): array
    {
        if (!empty($includedFields['jobDispatches'])) {
            $taskProcess->loadMissing(['jobDispatches.runningAuditRequest' => ['apiLogs', 'errorLogEntries']]);
        }

        return [
            'name'                  => $taskProcess->name,
            'operation'             => $taskProcess->operation,
            'activity'              => $taskProcess->activity,
            'percent_complete'      => $taskProcess->percent_complete,
            'status'                => $taskProcess->status,
            'started_at'            => $taskProcess->started_at,
            'stopped_at'            => $taskProcess->stopped_at,
            'failed_at'             => $taskProcess->failed_at,
            'completed_at'          => $taskProcess->completed_at,
            'timeout_at'            => $taskProcess->timeout_at,
            'job_dispatch_count'    => $taskProcess->job_dispatch_count,
            'input_artifact_count'  => $taskProcess->input_artifact_count,
            'output_artifact_count' => $taskProcess->output_artifact_count,
            'restart_count'         => $taskProcess->restart_count,
            'created_at'            => $taskProcess->created_at,
            'updated_at'            => $taskProcess->updated_at,

            'agentThread'     => fn($fields) => AgentThreadResource::make($taskProcess->agentThread, $fields),
            'inputArtifacts'  => fn($fields) => ArtifactResource::collection($taskProcess->inputArtifacts, $fields),
            'outputArtifacts' => fn($fields) => ArtifactResource::collection($taskProcess->outputArtifacts, $fields),
            'jobDispatches'   => fn($fields) => JobDispatchResource::collection($taskProcess->jobDispatches, $fields),
            'usage'           => fn($fields) => UsageSummaryResource::make($taskProcess->usageSummary, $fields),
            'taskRun'         => fn($fields) => TaskRunResource::make($taskProcess->taskRun, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'agentThread'     => true,
            'inputArtifacts'  => true,
            'outputArtifacts' => true,
            'jobDispatches'   => true,
            'usage'           => true,
        ]);
    }
}
