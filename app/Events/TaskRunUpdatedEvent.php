<?php

namespace App\Events;

use App\Models\Task\TaskRun;
use App\Resources\TaskDefinition\TaskRunResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskRunUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskRun $taskRun) { }

    public function broadcastOn()
    {
        return new PrivateChannel('TaskRun.' . $this->taskRun->taskDefinition->team_id);
    }

    public function broadcastAs()
    {
        return 'updated';
    }

    public function broadcastWith()
    {
        return TaskRunResource::make($this->taskRun);
    }
}
