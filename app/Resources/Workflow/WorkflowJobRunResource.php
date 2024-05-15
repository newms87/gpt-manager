<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJobRun;
use Flytedan\DanxLaravel\Resources\ActionResource;

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
            'status'       => $this->status,
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at'    => $this->failed_at,
            'created_at'   => $this->created_at,
        ];
    }
}
