<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskProcess;
use App\Resources\Agent\AgentThreadResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskProcessResource extends ActionResource
{
    public static function data(TaskProcess $taskProcess): array
    {
        return [
            'id'            => $taskProcess->id,
            'status'        => $taskProcess->status,
            'started_at'    => $taskProcess->started_at,
            'stopped_at'    => $taskProcess->stopped_at,
            'failed_at'     => $taskProcess->failed_at,
            'completed_at'  => $taskProcess->completed_at,
            'timeout_at'    => $taskProcess->timeout_at,
            'input_tokens'  => $taskProcess->input_tokens,
            'output_tokens' => $taskProcess->output_tokens,
            'input_cost'    => $taskProcess->input_cost,
            'output_cost'   => $taskProcess->output_cost,
            'total_cost'    => $taskProcess->total_cost,
            'created_at'    => $taskProcess->created_at,
            'updated_at'    => $taskProcess->updated_at,

            'agentThread' => fn($fields) => AgentThreadResource::make($taskProcess->agentThread, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*' => true,
        ]);
    }
}
