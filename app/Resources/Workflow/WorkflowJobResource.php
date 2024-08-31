<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowAssignment;
use App\Models\Workflow\WorkflowJob;
use App\Resources\Agent\AgentResource;
use App\Resources\Prompt\PromptSchemaResource;
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
            'workflow_tool' => $model->workflow_tool,
            'runs_count'    => $model->runs_count,
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
            'tasks_preview'  => $model->getTasksPreview(),
            'responseSchema' => PromptSchemaResource::make($model->responseSchema),
            'workflow'       => WorkflowResource::make($model->workflow),
            'dependencies'   => WorkflowJobDependencyResource::collection($model->dependencies),
            'assignments'    => WorkflowAssignmentResource::collection($assignments, fn(WorkflowAssignment $workflowAssignment) => [
                'agent' => AgentResource::make($workflowAssignment->agent),
            ]),
        ]);
    }
}
