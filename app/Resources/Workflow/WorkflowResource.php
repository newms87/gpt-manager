<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Workflow;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowResource extends ActionResource
{
    /**
     * @param Workflow $model
     */
    public static function data(Model $model, array $attributes = []): array
    {
        return static::make($model, [
            'id'          => $model->id,
            'name'        => $model->name,
            'description' => $model->description,
            'runs_count'  => $model->runs_count,
            'jobs_count'  => $model->jobs_count,
            'created_at'  => $model->created_at,
        ]);
    }

    /**
     * @param Workflow $model
     */
    public static function details(Model $model): array
    {
        return static::data($model, [
            'jobs' => WorkflowJobResource::collection($model->sortedAgentWorkflowJobs()->with(['dependencies', 'assignments'])->get()),
            'runs' => WorkflowRunResource::collection($model->workflowRuns()->with(['artifacts', 'workflowInput', 'sortedWorkflowJobRuns' => ['workflowJob', 'workflowRun']])->orderByDesc('id')->get()),
        ]);
    }
}
