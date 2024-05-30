<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;

/**
 * @mixin WorkflowRun
 * @property WorkflowRun $resource
 */
class WorkflowInputWorkflowRunDetailsResource extends WorkflowRunResource
{
    protected static string $type = 'WorkflowRun';

    public function data(): array
    {
        return [
                'workflowJobRuns' => WorkflowJobRunResource::collection($this->sortedWorkflowJobRuns()->get()),
            ] + parent::data();
    }
}
