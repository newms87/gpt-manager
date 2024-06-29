<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use App\Resources\Agent\AgentResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowResource extends ActionResource
{
    /**
     * @param Workflow $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'          => $model->id,
            'name'        => $model->name,
            'description' => $model->description,
            'runs_count'  => $model->runs_count,
            'jobs_count'  => $model->jobs_count,
            'created_at'  => $model->created_at,
        ];
    }

    /**
     * @param Workflow $model
     */
    public static function details(Model $model): array
    {
        $jobs = $model->sortedAgentWorkflowJobs()->with(['dependencies', 'workflowAssignments.agent'])->get();
        $runs = $model->workflowRuns()->with(['artifacts', 'workflowInput', 'sortedWorkflowJobRuns' => ['workflowJob', 'workflowRun']])->orderByDesc('id')->get();

        return static::make($model, [
            'jobs' => WorkflowJobResource::collection($jobs, fn(WorkflowJob $workflowJob) => [
                'dependencies' => WorkflowJobDependencyResource::collection($workflowJob->dependencies),
                'assignments'  => WorkflowAssignmentResource::collection($workflowJob->workflowAssignments, fn(WorkflowAssignment $workflowAssignment) => [
                    'agent' => AgentResource::make($workflowAssignment->agent),
                ]),
            ]),
            'runs' => WorkflowRunResource::collection($runs),
        ]);
    }
}
