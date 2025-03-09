<?php

namespace App\Resources\TaskWorkflow;

use App\Models\Task\TaskWorkflowRun;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Resources\ActionResource;

class TaskWorkflowRunResource extends ActionResource
{
    public static function data(TaskWorkflowRun $taskWorkflowRun): array
    {
        return [
            'id'           => $taskWorkflowRun->id,
            'name'         => $taskWorkflowRun->name,
            'status'       => $taskWorkflowRun->status,
            'started_at'   => $taskWorkflowRun->started_at,
            'stopped_at'   => $taskWorkflowRun->stopped_at,
            'failed_at'    => $taskWorkflowRun->failed_at,
            'completed_at' => $taskWorkflowRun->completed_at,
            'created_at'   => $taskWorkflowRun->created_at,
            'updated_at'   => $taskWorkflowRun->updated_at,
            'taskRuns'     => fn($fields) => TaskRunResource::collection($taskWorkflowRun->taskRuns, $fields),
        ];
    }
}
