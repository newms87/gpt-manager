<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin WorkflowRun
 * @property WorkflowRun $resource
 */
class WorkflowRunResource extends ActionResource
{
    protected static string $type = 'WorkflowRun';

    public function data(): array
    {
        $workflowJobRuns = $this->resolveFieldRelation('workflowJobRuns', ['workflowJobRuns', 'sortedWorkflowJobRuns'], fn() => $this->sortedWorkflowJobRuns()->with(['workflowJob'])->get());

        return [
            'id'              => $this->id,
            'workflow_id'     => $this->workflow_id,
            'workflow_name'   => $this->workflow->name,
            'status'          => $this->status,
            'started_at'      => $this->started_at,
            'completed_at'    => $this->completed_at,
            'failed_at'       => $this->failed_at,
            'created_at'      => $this->created_at,
            'usage'           => [
                'input_tokens'  => $this->getTotalInputTokens(),
                'output_tokens' => $this->getTotalOutputTokens(),
                'cost'          => $this->getTotalCost(),
            ],

            // Conditional Fields
            'artifacts'       => ArtifactResource::collection($this->resolveFieldRelation('artifacts')),
            'workflowInput'   => WorkflowInputResource::make($this->resolveFieldRelation('workflowInput')),
            'workflowJobRuns' => WorkflowJobRunResource::collection($workflowJobRuns),
        ];
    }
}
