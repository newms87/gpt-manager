<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin WorkflowRun
 * @property WorkflowRun $resource
 */
class WorkflowRunResource extends ActionResource
{
    protected static string $type = 'WorkflowRun';

    public function data(): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'inputSource'  => InputSourceResource::make($this->inputSource),
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
            'failed_at'    => $this->failed_at,
            'created_at'   => $this->created_at,
        ];
    }
}
