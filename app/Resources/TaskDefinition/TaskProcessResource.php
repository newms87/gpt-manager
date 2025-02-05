<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskProcess;
use App\Resources\Agent\AgentThreadResource;
use App\Resources\Audit\JobDispatchResource;
use App\Resources\Usage\UsageSummaryResource;
use App\Resources\Workflow\ArtifactResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskProcessResource extends ActionResource
{
    public static function data(TaskProcess $taskProcess): array
    {
        return [
            'id'                    => $taskProcess->id,
            'name'                  => $taskProcess->name,
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
            'created_at'            => $taskProcess->created_at,
            'updated_at'            => $taskProcess->updated_at,

            'agentThread'     => fn($fields) => AgentThreadResource::make($taskProcess->agentThread, $fields),
            'inputArtifacts'  => fn($fields) => ArtifactResource::collection($taskProcess->inputArtifacts, $fields),
            'outputArtifacts' => fn($fields) => ArtifactResource::collection($taskProcess->outputArtifacts, $fields),
            'jobDispatches'   => fn($fields) => JobDispatchResource::collection($taskProcess->jobDispatches, $fields),
            'usage'           => fn($fields) => UsageSummaryResource::make($taskProcess->usageSummary, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*' => true,
        ]);
    }
}
