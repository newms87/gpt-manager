<?php

namespace App\Events;

use App\Models\Task\TaskRun;
use App\Resources\TaskDefinition\TaskRunResource;
use Illuminate\Broadcasting\PrivateChannel;

class TaskRunUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TaskRun $taskRun, protected string $event)
    {
        parent::__construct($taskRun, $event);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('TaskRun.' . $this->taskRun->taskDefinition->team_id);
    }

    public function data(): array
    {
        return TaskRunResource::make($this->taskRun);
    }
}
