<?php

namespace App\Events;

use App\Models\Workflow\WorkflowRun;
use App\Resources\Audit\JobDispatchResource;
use Illuminate\Broadcasting\PrivateChannel;
use Newms87\Danx\Events\ModelSavedEvent;
use Newms87\Danx\Models\Job\JobDispatch;

class JobDispatchUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected JobDispatch $jobDispatch, protected string $event)
    {
        parent::__construct($jobDispatch, $event);
    }

    public function broadcastOn()
    {
        $channels = [];

        // Get workflow run ID from job dispatchables
        $dispatchable = \DB::table('job_dispatchables')
            ->where('job_dispatch_id', $this->jobDispatch->id)
            ->where('model_type', WorkflowRun::class)
            ->first();

        // TODO: make sure all this was implemented correctly
        if ($dispatchable) {
            // Check which users are subscribed to this workflow's job dispatches
            $workflowRun = WorkflowRun::find($dispatchable->model_id);

            if ($workflowRun) {
                $userIds = $workflowRun->workflowDefinition->team?->users
                    ->pluck('user_id')
                    ->toArray() ?? [];

                foreach($userIds as $userId) {
                    if (cache()->get('subscribe:workflow-job-dispatches:' . $userId . ':' . $workflowRun->id)) {
                        $channels[] = new PrivateChannel('JobDispatch.' . $userId);
                    }
                }
            }
        }

        return $channels;
    }

    public function data(): array
    {
        return JobDispatchResource::make($this->jobDispatch);
    }
}
