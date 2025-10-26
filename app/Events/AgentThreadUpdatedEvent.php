<?php

namespace App\Events;

use App\Models\Agent\AgentThread;
use App\Resources\Agent\AgentThreadResource;
use Newms87\Danx\Events\ModelSavedEvent;

class AgentThreadUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected AgentThread $agentThread, protected string $event)
    {
        parent::__construct(
            $agentThread,
            $event,
            AgentThreadResource::class,
            $agentThread->team_id
        );
    }

    protected function createdData(): array
    {
        return AgentThreadResource::make($this->agentThread, [
            '*'         => false,
            'name'      => true,
            'timestamp' => true,
            'can'       => true,
        ]);
    }

    protected function updatedData(): array
    {
        return AgentThreadResource::make($this->agentThread, [
            '*'                => false,
            'name'             => true,
            'summary'          => true,
            'is_running'       => true,
            'usage'            => true,
            'audit_request_id' => true,
            'timestamp'        => true,
        ]);
    }
}
