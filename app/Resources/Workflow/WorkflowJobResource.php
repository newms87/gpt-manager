<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use App\Resources\Agent\AgentResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobResource extends ActionResource
{
    /**
     * @param WorkflowJob $model
     */
    public static function data(Model $model): array
    {
        return [
            'id'            => $model->id,
            'name'          => $model->name,
            'description'   => $model->description,

            // TODO: refactor to countable
            'runs_count'    => $model->workflowJobRuns()->count(),
            'use_input'     => $model->use_input,
            'workflow_tool' => $model->workflow_tool,
            'created_at'    => $model->created_at,
        ];
    }

    /**
     * @param WorkflowJob $model
     */
    public static function details(Model $model): array
    {
        $assignments = $model->workflowAssignments()->with(['agent'])->get();

        return static::make($model, [
            'tasks_preview' => $model->getTasksPreview(),
            'workflow'      => WorkflowResource::make($model->workflow),
            'dependencies'  => WorkflowJobDependencyResource::collection($model->dependencies),
            'assignments'   => WorkflowAssignmentResource::collection($assignments, fn(WorkflowAssignment $workflowAssignment) => [
                'agent' => AgentResource::make($workflowAssignment->agent),
            ]),
        ]);
    }
}
