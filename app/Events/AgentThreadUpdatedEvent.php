<?php

namespace App\Events;

use App\Models\Agent\AgentThread;
use App\Resources\Agent\AgentThreadResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentThreadUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AgentThread $agentThread) { }

    public function broadcastOn()
    {
        return new PrivateChannel('AgentThread.' . $this->agentThread->team_id);
    }

    public function broadcastAs()
    {
        return 'updated';
    }

    public function broadcastWith()
    {
        return AgentThreadResource::details($this->agentThread, ['messages' => ['files' => ['transcodes' => true]]]);
    }
}
