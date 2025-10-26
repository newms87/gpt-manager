<?php

namespace App\Events;

use App\Models\Task\TaskDefinition;
use App\Traits\BroadcastsWithSubscriptions;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClaudeCodeGenerationEvent implements ShouldBroadcast
{
    use BroadcastsWithSubscriptions, Dispatchable, SerializesModels;

    public function __construct(
        public TaskDefinition $taskDefinition,
        public string $event,
        public array $data = []
    ) {
    }

    public function broadcastOn()
    {
        $resourceType = 'ClaudeCodeGeneration';
        $teamId       = $this->taskDefinition->team_id;

        $userIds = $this->getSubscribedUsers($resourceType, $teamId, $this->taskDefinition, TaskDefinition::class);

        return $this->getSubscribedChannels($resourceType, $teamId, $userIds);
    }

    public function broadcastAs()
    {
        return $this->event;
    }

    public function broadcastWith()
    {
        return array_merge([
            'task_definition_id' => $this->taskDefinition->id,
            'event'              => $this->event,
            'timestamp'          => now()->toISOString(),
        ], $this->data);
    }
}
