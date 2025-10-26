<?php

namespace App\Resources\Workflow;

use App\Models\Workflow\WorkflowListener;
use Newms87\Danx\Resources\ActionResource;

class WorkflowListenerResource extends ActionResource
{
    public static function data(WorkflowListener $workflowListener): array
    {
        return [
            'id'              => $workflowListener->id,
            'workflow_type'   => $workflowListener->workflow_type,
            'status'          => $workflowListener->status,
            'workflow_run_id' => $workflowListener->workflow_run_id,
            'listener_type'   => $workflowListener->listener_type,
            'listener_id'     => $workflowListener->listener_id,
            'metadata'        => $workflowListener->metadata,
            'started_at'      => $workflowListener->started_at,
            'completed_at'    => $workflowListener->completed_at,
            'failed_at'       => $workflowListener->failed_at,
            'created_at'      => $workflowListener->created_at,
            'updated_at'      => $workflowListener->updated_at,

            // Status helper methods
            'is_pending'   => $workflowListener->isPending(),
            'is_running'   => $workflowListener->isRunning(),
            'is_completed' => $workflowListener->isCompleted(),
            'is_failed'    => $workflowListener->isFailed(),
            'is_finished'  => $workflowListener->isFinished(),

            // Relationships
            'workflow_run' => fn($fields) => WorkflowRunResource::make($workflowListener->workflowRun, $fields),
        ];
    }
}
