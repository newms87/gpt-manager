<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowTask;
use App\Resources\Agent\ThreadResource;
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
            'job_name'     => $this->workflowJob->name,
            'group'        => $this->group,
            'agent_name'   => $this->workflowAssignment->agent->name,
            'model'        => $this->workflowAssignment->agent->model,
            'status'       => $this->status,
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at'    => $this->failed_at,
            'job_logs'     => $this->jobDispatch?->runningAuditRequest?->logs,
            'thread'       => $this->thread ? ThreadResource::make($this->thread) : null,
            'created_at'   => $this->created_at,
        ];
    }
}
