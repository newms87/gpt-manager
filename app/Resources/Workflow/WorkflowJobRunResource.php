<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobRun;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobRunResource extends ActionResource
{
    public static function data(WorkflowJobRun $workflowJobRun): array
    {
        return [
            'id'           => $workflowJobRun->id,
            'name'         => $workflowJobRun->workflowJob?->name . ' (' . $workflowJobRun->id . ')',
            'status'       => $workflowJobRun->status,
            'started_at'   => $workflowJobRun->started_at,
            'completed_at' => $workflowJobRun->completed_at,
            'failed_at'    => $workflowJobRun->failed_at,
            'created_at'   => $workflowJobRun->created_at,
            'usage'        => [
                'input_tokens'  => $workflowJobRun->getTotalInputTokens(),
                'output_tokens' => $workflowJobRun->getTotalOutputTokens(),
                'total_cost'    => $workflowJobRun->getTotalCost(),
            ],
            'depth'        => fn() => $workflowJobRun->workflowJob?->dependency_level,
            'workflowJob'  => fn($fields) => WorkflowJobResource::make($workflowJobRun->workflowJob, $fields),
            'tasks'        => fn($fields) => WorkflowTaskResource::collection($workflowJobRun->tasks, $fields),
        ];
    }
}
