<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowRun;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class WorkflowRunResource extends ActionResource
{
    public static function data(WorkflowRun $workflowRun): array
    {
        return [
            'id'              => $workflowRun->id,
            'workflow_id'     => $workflowRun->workflow_id,
            'workflow_name'   => $workflowRun->workflow?->name,
            'input_id'        => $workflowRun->workflowInput?->id,
            'input_name'      => $workflowRun->workflowInput?->name,
            'status'          => $workflowRun->status,
            'job_runs_count'  => $workflowRun->job_runs_count,
            'artifacts_count' => $workflowRun->artifacts_count,
            'started_at'      => $workflowRun->started_at,
            'completed_at'    => $workflowRun->completed_at,
            'failed_at'       => $workflowRun->failed_at,
            'created_at'      => $workflowRun->created_at,
            'usage'           => [
                'input_tokens'  => $workflowRun->getTotalInputTokens(),
                'output_tokens' => $workflowRun->getTotalOutputTokens(),
                'total_cost'    => $workflowRun->getTotalCost(),
            ],
            'workflowInput'   => fn($fields) => WorkflowInputResource::make($workflowRun->workflowInput, $fields),
            'artifacts'       => fn() => ArtifactResource::collection($workflowRun->artifacts->load('storedFiles.transcodes'), ['*' => true]),
            'workflowJobRuns' => fn($fields) => WorkflowJobRunResource::collection($workflowRun->sortedWorkflowJobRuns->load(['workflowJob', 'tasks.jobDispatch.runningAuditRequest', 'tasks.thread.sortedMessages.storedFiles.transcodes']), $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            '*'               => true,
            'workflowJobRuns' => [
                '*'     => true,
                'tasks' => [
                    '*'      => true,
                    'thread' => [
                        'messages' => [
                            'files' => [
                                'transcodes' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
