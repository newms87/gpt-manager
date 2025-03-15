<?php

namespace App\Traits;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;

/**
 * @mixin TaskProcess
 */
trait HasWorkflowStatesTrait
{
    public function isPending(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_RUNNING;
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

    public function isTimeout(): bool
    {
        return $this->timeout_at !== null;
    }

    public function isAwaitingRunner(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_DISPATCHED;
    }

    public function isFinished(): bool
    {
        return ($this->isCompleted() || $this->isFailed() || $this->isStopped() || $this->isTimeout()) && !$this->isAwaitingRunner();
    }

    public function canContinue(): bool
    {
        return !$this->isFinished();
    }

    public function computeStatus(): static
    {
        if (!$this->isStarted()) {
            $this->status = WorkflowStatesContract::STATUS_PENDING;
        } elseif ($this->isFailed()) {
            $this->status = WorkflowStatesContract::STATUS_FAILED;
        } elseif ($this->isStopped()) {
            $this->status = WorkflowStatesContract::STATUS_STOPPED;
        } elseif (!$this->isCompleted()) {
            $this->status = WorkflowStatesContract::STATUS_RUNNING;
        } else {
            $this->status = WorkflowStatesContract::STATUS_COMPLETED;
        }

        return $this;
    }
}
