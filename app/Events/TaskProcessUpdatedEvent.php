<?php

namespace App\Events;

use App\Models\Task\TaskProcess;
use App\Resources\TaskDefinition\TaskProcessResource;
use Newms87\Danx\Events\ModelSavedEvent;

class TaskProcessUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TaskProcess $taskProcess, protected string $event)
    {
        parent::__construct(
            $taskProcess,
            $event,
            TaskProcessResource::class,
            $taskProcess->taskRun?->taskDefinition?->team_id
        );
    }

    protected function createdData(): array
    {
        return TaskProcessResource::make($this->taskProcess, [
            '*'          => false,
            'name'       => true,
            'status'     => true,
            'created_at' => true,
        ]);
    }

    protected function updatedData(): array
    {
        return TaskProcessResource::make($this->taskProcess, [
            '*'                     => false,
            'activity'              => true,
            'percent_complete'      => true,
            'status'                => true,
            'started_at'            => true,
            'stopped_at'            => true,
            'failed_at'             => true,
            'completed_at'          => true,
            'timeout_at'            => true,
            'job_dispatch_count'    => true,
            'input_artifact_count'  => true,
            'output_artifact_count' => true,
            'updated_at'            => true,
        ]);
    }
}
