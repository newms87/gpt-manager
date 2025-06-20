<?php

namespace App\Events;

use App\Models\Task\TaskDefinition;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClaudeCodeGenerationEvent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TaskDefinition $taskDefinition,
        public string $event,
        public array $data = []
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('ClaudeCodeGeneration.' . $this->taskDefinition->team_id);
    }

    public function broadcastAs()
    {
        return $this->event;
    }

    public function broadcastWith()
    {
        return array_merge([
            'task_definition_id' => $this->taskDefinition->id,
            'event' => $this->event,
            'timestamp' => now()->toISOString(),
        ], $this->data);
    }
}