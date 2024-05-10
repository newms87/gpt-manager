<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Workflow;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin Workflow
 * @property Workflow $resource
 */
class WorkflowResource extends ActionResource
{
    protected static string $type = 'Workflow';

    public function data(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'run_count'   => $this->workflowRuns()->count(),
            'job_count'   => $this->workflowJobs()->count(),

            'created_at' => $this->created_at,
        ];
    }
}
