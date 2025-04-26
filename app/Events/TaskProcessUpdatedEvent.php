<?php

namespace App\Events;

use App\Models\Task\TaskProcess;
use App\Models\Team\Team;
use App\Resources\TaskDefinition\TaskProcessResource;
use Illuminate\Broadcasting\PrivateChannel;

class TaskProcessUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TaskProcess $taskProcess, protected string $event)
    {
        parent::__construct($taskProcess, $event);
    }

    public function broadcastOn()
    {
        $channels = [];
        $teamId   = $this->taskProcess->taskRun->taskDefinition->team_id;
        $userIds  = Team::query()->where('teams.id', $teamId)->join('team_user', 'team_id', 'teams.id')->pluck('user_id')->toArray();

        foreach($userIds as $userId) {
            if (cache()->get('subscribe:task-run-processes:' . $userId)) {
                $channels[] = new PrivateChannel('TaskProcess.' . $userId);
            }
        }

        return $channels;
    }

    public function data(): array
    {
        return TaskProcessResource::make($this->taskProcess);
    }
}
