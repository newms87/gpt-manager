<?php

namespace App\Events;

use App\Models\Agent\AgentThreadRun;
use App\Resources\Agent\AgentThreadRunResource;
use Newms87\Danx\Events\ModelSavedEvent;

class AgentThreadRunUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected AgentThreadRun $agentThreadRun, protected string $event)
    {
        parent::__construct(
            $agentThreadRun,
            $event,
            AgentThreadRunResource::class,
            $agentThreadRun->agentThread->team_id
        );
    }

    protected function createdData(): array
    {
        return AgentThreadRunResource::make($this->agentThreadRun, [
            '*'                    => false,
            'agent_thread_id'      => true,
            'status'               => true,
            'response_format'      => true,
            'response_schema_id'   => true,
            'response_fragment_id' => true,
        ]);
    }

    protected function updatedData(): array
    {
        return AgentThreadRunResource::make($this->agentThreadRun, [
            '*'            => false,
            'status'       => true,
            'started_at'   => true,
            'completed_at' => true,
            'failed_at'    => true,
            'refreshed_at' => true,
            'usage'        => true,
        ]);
    }
}
