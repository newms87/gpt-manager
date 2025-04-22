<?php

namespace App\Events;

use App\Models\Workflow\WorkflowRun;
use App\Resources\Workflow\WorkflowRunResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowRunUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WorkflowRun $workflowRun) { }

    public function broadcastOn()
    {
        return new PrivateChannel('WorkflowRun.' . $this->workflowRun->workflowDefinition->team_id);
    }

    public function broadcastAs()
    {
        return 'updated';
    }

    public function broadcastWith()
    {
        return WorkflowRunResource::make($this->workflowRun);
    }
}
