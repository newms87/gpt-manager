<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowTask;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin WorkflowTask
 * @property WorkflowTask $resource
 */
class WorkflowTaskResource extends ActionResource
{
    protected static string $type = 'WorkflowTask';

    public function data(): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at'    => $this->failed_at,
            'job_logs'     => $this->jobDispatch?->runningAuditRequest?->logs,
            'created_at'   => $this->created_at,
        ];
    }
}
