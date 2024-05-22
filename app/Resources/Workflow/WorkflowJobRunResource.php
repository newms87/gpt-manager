<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobRun;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin WorkflowJobRun
 * @property WorkflowJobRun $resource
 */
class WorkflowJobRunResource extends ActionResource
{
    protected static string $type = 'WorkflowJobRun';

    public function data(): array
    {
        return [
            'id'           => $this->id,
            'workflowJob'  => WorkflowJobResource::make($this->workflowJob),
            'tasks'        => WorkflowTaskResource::collection($this->tasks),
            'status'       => $this->status,
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at'    => $this->failed_at,
            'created_at'   => $this->created_at,
            'usage'        => [
                'input_tokens'  => $this->getTotalInputTokens(),
                'output_tokens' => $this->getTotalOutputTokens(),
                'cost'          => $this->getTotalCost(),
            ],
        ];
    }
}
