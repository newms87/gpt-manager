<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Workflow;
use Newms87\Danx\Resources\ActionResource;

/**
 * @mixin Workflow
 * @property Workflow $resource
 */
class WorkflowResource extends ActionResource
{
    protected static string $type = 'Workflow';

    public function data(): array
    {
        $jobs = $this->resolveFieldRelation('jobs', ['workflowJobs', 'sortedAgentWorkflowJobs'], fn() => $this->sortedAgentWorkflowJobs()->with(['dependencies', 'assignments'])->get());
        $runs = $this->resolveFieldRelation('runs', ['workflowRuns'], fn() => $this->workflowRuns()->with(['artifacts', 'workflowInput', 'sortedWorkflowJobRuns' => ['workflowJob', 'workflowRun']])->orderByDesc('id')->get());

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'runs_count'  => $this->runs_count,
            'jobs_count'  => $this->jobs_count,
            'created_at'  => $this->created_at,
            'jobs'        => WorkflowJobResource::collection($jobs),
            'runs'        => WorkflowRunResource::collection($runs),
        ];
    }
}
