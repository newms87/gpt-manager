<?php

namespace App\Traits;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;

/**
 * @mixin TaskProcess
 */
trait HasWorkflowStatesTrait
{
    public function isStatusPending(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_PENDING;
    }

    public function isStatusRunning(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_RUNNING;
    }

    public function isStatusDispatched(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_DISPATCHED;
    }

    public function isStatusTimeout(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_TIMEOUT;
    }

    public function isStatusFailed(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_FAILED;
    }

    public function isStatusStopped(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_STOPPED;
    }

    public function isStatusSkipped(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_SKIPPED;
    }

    public function isStatusCompleted(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_COMPLETED;
    }

    public function isStarted(): bool
    {
        return $this->started_at !== null;
    }

    public function isStopped(): bool
    {
        return $this->stopped_at !== null;
    }

    public function isFailed(): bool
    {
        return $this->failed_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isSkipped(): bool
    {
        return $this->skipped_at !== null;
    }

    public function isTimedout(): bool
    {
        return $this->timeout_at !== null;
    }

    public function isFinished(): bool
    {
        return ($this->isCompleted() || $this->isFailed() || $this->isStopped() || $this->isSkipped() || $this->isTimedout()) && !$this->isStatusDispatched();
    }

    public function canContinue(): bool
    {
        return !$this->isFinished();
    }

    public function computeStatus(): static
    {
        if ($this->isFailed()) {
            $this->status = WorkflowStatesContract::STATUS_FAILED;
        } elseif ($this->isStopped()) {
            $this->status = WorkflowStatesContract::STATUS_STOPPED;
        } elseif ($this->isSkipped()) {
            $this->status = WorkflowStatesContract::STATUS_SKIPPED;
        } elseif (!$this->isStarted()) {
            $this->status = WorkflowStatesContract::STATUS_PENDING;
        } elseif (!$this->isCompleted()) {
            $this->status = WorkflowStatesContract::STATUS_RUNNING;
        } else {
            $this->status = WorkflowStatesContract::STATUS_COMPLETED;
        }

        return $this;
    }
}
