<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use App\Resources\InputSource\InputSourceResource;

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
                'inputSource'     => InputSourceResource::make($this->inputSource),
                'workflowJobRuns' => WorkflowJobRunResource::collection($this->workflowJobRuns),
            ] + parent::data();
    }
}
