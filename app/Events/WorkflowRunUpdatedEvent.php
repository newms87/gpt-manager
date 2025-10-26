<?php

namespace App\Events;

use App\Models\Workflow\WorkflowRun;
use App\Resources\Workflow\WorkflowRunResource;
use Newms87\Danx\Events\ModelSavedEvent;

class WorkflowRunUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected WorkflowRun $workflowRun, protected string $event)
    {
        parent::__construct(
            $workflowRun,
            $event,
            WorkflowRunResource::class,
            $workflowRun->workflowDefinition?->team_id
        );
    }

    public function getWorkflowRun(): WorkflowRun
    {
        return $this->workflowRun;
    }

    protected function createdData(): array
    {
        return WorkflowRunResource::make($this->workflowRun, [
            '*'                      => false,
            'name'                   => true,
            'status'                 => true,
            'workflow_definition_id' => true,
            'created_at'             => true,
            'started_at'             => true,
        ]);
    }

    protected function updatedData(): array
    {
        return WorkflowRunResource::make($this->workflowRun, [
            '*'                      => false,
            'name'                   => true, // Simple string - needed for display
            'workflow_definition_id' => true, // Foreign key - needed for grouping/filtering
            'status'                 => true,
            'progress_percent'       => true,
            'active_workers_count'   => true,
            'error_count'            => true,
            'completed_tasks'        => true,
            'stopped_at'             => true,
            'failed_at'              => true,
            'completed_at'           => true,
            'updated_at'             => true,
        ]);
    }
}
