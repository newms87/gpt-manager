<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\Workflow;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowResource extends ActionResource
{
    public static function data(Workflow $workflow): array
    {
        return [
            'id'          => $workflow->id,
            'name'        => $workflow->name,
            'description' => $workflow->description,
            'runs_count'  => $workflow->runs_count,
            'jobs_count'  => $workflow->jobs_count,
            'created_at'  => $workflow->created_at,
            'jobs'        => fn($fields) => WorkflowJobResource::collection($workflow->sortedAgentWorkflowJobs->load(['dependencies', 'workflowAssignments.agent']), $fields),
            'runs'        => fn($fields) => WorkflowRunResource::collection($workflow->workflowRuns()
                // NOTE: load all these relationships eagerly to compute task tokens / costs!
                ->with(['artifacts', 'sortedWorkflowJobRuns' => ['workflowJob', 'tasks' => ['jobDispatch.runningAuditRequest', 'thread.messages.storedFiles.transcodes']]])
                ->orderByDesc('id')
                ->limit(10)
                ->get(), $fields),
        ];
    }

    public static function details(Model $model): array
    {
        return static::make($model, [
            '*'    => true,
            'jobs' => [
                'tasks_preview'  => true,
                'responseSchema' => true,
                'dependencies'   => true,
                'assignments'    => [
                    'agent' => true,
                ],
            ],
        ]);
    }
}
