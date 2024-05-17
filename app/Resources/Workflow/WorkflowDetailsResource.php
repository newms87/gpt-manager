<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Workflow;

/**
 * @mixin Workflow
 * @property Workflow $resource
 */
class WorkflowDetailsResource extends WorkflowResource
{
    public function data(): array
    {
        return [
                'jobs' => WorkflowJobResource::collection($this->workflowJobs),
                'runs' => WorkflowRunDetailsResource::collection($this->workflowRuns),
            ] + parent::data();
    }
}
