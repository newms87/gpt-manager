<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskRun;
use App\Resources\Usage\UsageSummaryResource;
use App\Resources\Workflow\ArtifactResource;
use Newms87\Danx\Resources\ActionResource;

class TaskRunResource extends ActionResource
{
    public static function data(TaskRun $taskRun): array
    {
        return [
            'id'                     => $taskRun->id,
            'name'                   => $taskRun->name,
            'step'                   => $taskRun->step,
            'percent_complete'       => $taskRun->percent_complete,
            'status'                 => $taskRun->status,
            'started_at'             => $taskRun->started_at,
            'completed_at'           => $taskRun->completed_at,
            'stopped_at'             => $taskRun->stopped_at,
            'failed_at'              => $taskRun->failed_at,
            'process_count'          => $taskRun->process_count,
            'created_at'             => $taskRun->created_at,
            'updated_at'             => $taskRun->updated_at,
            'task_definition_id'     => $taskRun->task_definition_id,
            'workflow_node_id'       => $taskRun->workflow_node_id,
            'workflow_run_id'        => $taskRun->workflow_run_id,
            'input_artifacts_count'  => $taskRun->input_artifacts_count,
            'output_artifacts_count' => $taskRun->output_artifacts_count,

            'taskDefinition'  => fn($fields) => TaskDefinitionResource::make($taskRun->taskDefinition, $fields),
            'processes'       => fn($fields) => TaskProcessResource::collection($taskRun->taskProcesses, $fields),
            'inputArtifacts'  => fn($fields) => ArtifactResource::collection($taskRun->inputArtifacts, $fields),
            'outputArtifacts' => fn($fields) => ArtifactResource::collection($taskRun->outputArtifacts, $fields),
            'usage'           => fn($fields) => UsageSummaryResource::make($taskRun->usageSummary, $fields),
        ];
    }
}
