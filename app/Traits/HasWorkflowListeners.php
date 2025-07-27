<?php

namespace App\Traits;

use App\Models\Workflow\WorkflowListener;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWorkflowListeners
{
    public function workflowListeners(): MorphMany
    {
        return $this->morphMany(WorkflowListener::class, 'listener');
    }

    public function getWorkflowListener(string $workflowType): ?WorkflowListener
    {
        return $this->workflowListeners()
            ->forWorkflowType($workflowType)
            ->first();
    }

    public function hasWorkflowOfType(string $workflowType): bool
    {
        return $this->workflowListeners()
            ->forWorkflowType($workflowType)
            ->exists();
    }

    public function isWorkflowRunning(string $workflowType): bool
    {
        return $this->workflowListeners()
            ->forWorkflowType($workflowType)
            ->running()
            ->exists();
    }

    public function isWorkflowCompleted(string $workflowType): bool
    {
        return $this->workflowListeners()
            ->forWorkflowType($workflowType)
            ->completed()
            ->exists();
    }

    public function isWorkflowFailed(string $workflowType): bool
    {
        return $this->workflowListeners()
            ->forWorkflowType($workflowType)
            ->failed()
            ->exists();
    }

    public function getWorkflowStatus(string $workflowType): ?string
    {
        return $this->getWorkflowListener($workflowType)?->status;
    }

    public function getRunningWorkflows(): \Illuminate\Support\Collection
    {
        return $this->workflowListeners()->running()->get();
    }

    public function hasRunningWorkflows(): bool
    {
        return $this->workflowListeners()->running()->exists();
    }

    public function getLatestWorkflowOfType(string $workflowType): ?WorkflowListener
    {
        return $this->workflowListeners()
            ->forWorkflowType($workflowType)
            ->latest()
            ->first();
    }
}