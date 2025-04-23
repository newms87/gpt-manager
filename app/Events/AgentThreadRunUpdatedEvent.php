<?php

namespace App\Events;

use App\Models\Agent\AgentThreadRun;
use App\Resources\Agent\AgentThreadRunResource;
use Illuminate\Broadcasting\PrivateChannel;

class AgentThreadRunUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected AgentThreadRun $agentThreadRun, protected string $event)
    {
        parent::__construct($agentThreadRun, $event);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('AgentThreadRun.' . $this->agentThreadRun->agentThread->team_id);
    }

    public function data(): array
    {
        return AgentThreadRunResource::make($this->agentThreadRun);
    }
}
