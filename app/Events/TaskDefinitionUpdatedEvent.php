<?php

namespace App\Events;

use App\Models\Task\TaskDefinition;
use App\Resources\TaskDefinition\TaskDefinitionResource;
use Newms87\Danx\Events\ModelSavedEvent;

class TaskDefinitionUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TaskDefinition $taskDefinition, protected string $event)
    {
        parent::__construct(
            $taskDefinition,
            $event,
            TaskDefinitionResource::class,
            $taskDefinition->team_id
        );
    }

    protected function createdData(): array
    {
        return TaskDefinitionResource::make($this->taskDefinition, [
            '*'                => false,
            'name'             => true,
            'task_runner_name' => true,
            'created_at'       => true,
        ]);
    }

    protected function updatedData(): array
    {
        return TaskDefinitionResource::make($this->taskDefinition, [
            '*'          => false,
            'updated_at' => true,
        ]);
    }
}
