<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskRun;
use App\Resources\Usage\UsageSummaryResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskRunResource extends ActionResource
{
    public static function data(TaskRun $taskRun): array
    {
        return [
            'id'            => $taskRun->id,
            'status'        => $taskRun->status,
            'started_at'    => $taskRun->started_at,
            'completed_at'  => $taskRun->completed_at,
            'stopped_at'    => $taskRun->stopped_at,
            'failed_at'     => $taskRun->failed_at,
            'process_count' => $taskRun->process_count,
            'created_at'    => $taskRun->created_at,
            'updated_at'    => $taskRun->updated_at,

            'taskDefinition' => fn($fields) => TaskDefinitionResource::make($taskRun->taskDefinition, $fields),
            'processes'      => fn($fields) => TaskProcessResource::collection($taskRun->taskProcesses, $fields),
            'usage'          => fn($fields) => UsageSummaryResource::make($taskRun->usageSummary, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*' => true,
        ]);
    }
}
