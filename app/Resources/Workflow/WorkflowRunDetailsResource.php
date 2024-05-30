<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;

/**
 * @mixin WorkflowRun
 * @property WorkflowRun $resource
 */
class WorkflowRunDetailsResource extends WorkflowRunResource
{
    protected static string $type = 'WorkflowRun';

    public function data(): array
    {
        return [
                'workflowInput'   => WorkflowInputResource::make($this->workflowInput),
                'workflowJobRuns' => WorkflowJobRunResource::collection($this->sortedWorkflowJobRuns()->get()),
            ] + parent::data();
    }
}
