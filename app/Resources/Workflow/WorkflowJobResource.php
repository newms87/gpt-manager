<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowJob;
use App\Resources\Prompt\PromptSchemaResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowJobResource extends ActionResource
{
    public static function data(WorkflowJob $workflowJob): array
    {
        return [
            'id'            => $workflowJob->id,
            'name'          => $workflowJob->name,
            'description'   => $workflowJob->description,
            'workflow_tool' => $workflowJob->workflow_tool,
            'runs_count'    => $workflowJob->runs_count,
            'created_at'    => $workflowJob->created_at,

            'tasks_preview'  => fn() => $workflowJob->getTasksPreview(),
            'workflow'       => fn($fields) => WorkflowResource::make($workflowJob->workflow, $fields),
            'responseSchema' => fn($fields) => PromptSchemaResource::make($workflowJob->responseSchema, $fields),
            'dependencies'   => fn($fields) => WorkflowJobDependencyResource::collection($workflowJob->dependencies, $fields),
            'assignments'    => fn($fields) => WorkflowAssignmentResource::collection($workflowJob->workflowAssignments->load(['agent']), $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return parent::details($model, $includeFields ?? [
            '*'           => true,
            'assignments' => [
                'agent' => true,
            ],
        ]);
    }
}
