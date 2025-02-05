<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskRun;
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
            'input_tokens'  => $taskRun->input_tokens,
            'output_tokens' => $taskRun->output_tokens,
            'input_cost'    => $taskRun->input_cost,
            'output_cost'   => $taskRun->output_cost,
            'total_cost'    => $taskRun->total_cost,
            'created_at'    => $taskRun->created_at,
            'updated_at'    => $taskRun->updated_at,

            'processes' => fn($fields) => TaskProcessResource::collection($taskRun->taskProcesses, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*' => true,
        ]);
    }
}
