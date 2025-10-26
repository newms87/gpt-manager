<?php

namespace App\Events;

use App\Models\Task\TaskRun;
use App\Resources\TaskDefinition\TaskRunResource;
use Newms87\Danx\Events\ModelSavedEvent;

class TaskRunUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TaskRun $taskRun, protected string $event)
    {
        parent::__construct(
            $taskRun,
            $event,
            TaskRunResource::class,
            $taskRun->taskDefinition?->team_id
        );
    }

    protected function createdData(): array
    {
        return TaskRunResource::make($this->taskRun, [
            '*'                  => false,
            'name'               => true,
            'status'             => true,
            'task_definition_id' => true,
            'workflow_node_id'   => true,
            'workflow_run_id'    => true,
            'created_at'         => true,
        ]);
    }

    protected function updatedData(): array
    {
        return TaskRunResource::make($this->taskRun, [
            '*'                      => false,
            'status'                 => true,
            'step'                   => true,
            'percent_complete'       => true,
            'started_at'             => true,
            'completed_at'           => true,
            'stopped_at'             => true,
            'failed_at'              => true,
            'process_count'          => true,
            'error_count'            => true,
            'input_artifacts_count'  => true,
            'output_artifacts_count' => true,
            'updated_at'             => true,
        ]);
    }
}
