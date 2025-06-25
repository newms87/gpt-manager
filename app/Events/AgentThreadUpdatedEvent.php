<?php

namespace App\Events;

use App\Models\Agent\AgentThread;
use App\Resources\Agent\AgentThreadResource;
use Illuminate\Broadcasting\PrivateChannel;
use Newms87\Danx\Events\ModelSavedEvent;

class AgentThreadUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected AgentThread $agentThread, protected string $event)
    {
        parent::__construct($agentThread, $event);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('AgentThread.' . $this->agentThread->team_id);
    }

    public function data(): array
    {
        // Send minimal thread data via WebSocket, frontend will fetch full data as needed
        return AgentThreadResource::make($this->agentThread);
    }
}
