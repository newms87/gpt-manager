<?php

namespace App\Resources\InputSource;

use App\Models\Workflow\WorkflowRun;
use App\Resources\Workflow\WorkflowJobRunResource;
use App\Resources\Workflow\WorkflowRunResource;

/**
 * @mixin WorkflowRun
 * @property WorkflowRun $resource
 */
class InputSourceWorkflowRunDetailsResource extends WorkflowRunResource
{
    protected static string $type = 'WorkflowRun';

    public function data(): array
    {
        return [
                'workflowJobRuns' => WorkflowJobRunResource::collection($this->workflowJobRuns),
            ] + parent::data();
    }
}
