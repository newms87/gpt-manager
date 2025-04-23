<?php

namespace App\Events;

use App\Models\Workflow\WorkflowRun;
use App\Resources\Workflow\WorkflowRunResource;
use Illuminate\Broadcasting\PrivateChannel;

class WorkflowRunUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected WorkflowRun $workflowRun, protected string $event)
    {
        parent::__construct($workflowRun, $event);
    }

    public function broadcastOn()
    {
        return new PrivateChannel('WorkflowRun.' . $this->workflowRun->workflowDefinition->team_id);
    }

    public function data(): array
    {
        return WorkflowRunResource::make($this->workflowRun);
    }
}
