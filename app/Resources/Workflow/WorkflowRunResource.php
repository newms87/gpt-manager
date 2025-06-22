<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Resources\ActionResource;

class WorkflowRunResource extends ActionResource
{
    public static function data(WorkflowRun $workflowRun): array
    {
        return [
            'id'                   => $workflowRun->id,
            'name'                 => $workflowRun->name,
            'status'               => $workflowRun->status,
            'active_workers_count' => $workflowRun->active_workers_count,
            'started_at'           => $workflowRun->started_at,
            'stopped_at'           => $workflowRun->stopped_at,
            'failed_at'            => $workflowRun->failed_at,
            'completed_at'         => $workflowRun->completed_at,
            'created_at'           => $workflowRun->created_at,
            'updated_at'           => $workflowRun->updated_at,
            'taskRuns'             => fn($fields) => TaskRunResource::collection($workflowRun->taskRuns, $fields),
        ];
    }
}
