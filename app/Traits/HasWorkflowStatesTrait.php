<?php

namespace App\Traits;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;
use Exception;

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


    public function isStatusTimeout(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_TIMEOUT;
    }

    public function isStatusFailed(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_FAILED;
    }

    public function isStatusIncomplete(): bool
    {
        return $this->status === WorkflowStatesContract::STATUS_INCOMPLETE;
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

    public function isTimeout(): bool
    {
        return $this->timeout_at !== null;
    }

    public function isIncomplete(): bool
    {
        return $this->incomplete_at !== null;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isStopped() || $this->isSkipped();
    }

    public function canContinue(): bool
    {
        return !$this->isFinished();
    }

    public function computeStatus(): static
    {
        if (!$this->isStarted() && ($this->isCompleted() || $this->isFailed() || $this->isTimeout() || $this->isIncomplete())) {
            $timestamps = 'completed_at=' . $this->completed_at . "\n" .
                'failed_at=' . $this->failed_at . "\n" .
                'incomplete_at=' . $this->incomplete_at . "\n" .
                'timeout_at=' . $this->timeout_at . "\n" .
                'started_at=' . $this->started_at . "\n";

            throw new Exception("State Validation Error: The state is post-run state without being started: $this\n$timestamps");
        }

        if ($this->isFailed()) {
            $this->status = WorkflowStatesContract::STATUS_FAILED;
        } elseif ($this->isIncomplete()) {
            $this->status = WorkflowStatesContract::STATUS_INCOMPLETE;
        } elseif ($this->isStopped()) {
            $this->status = WorkflowStatesContract::STATUS_STOPPED;
        } elseif ($this->isSkipped()) {
            $this->status = WorkflowStatesContract::STATUS_SKIPPED;
        } elseif ($this->isCompleted()) {
            $this->status = WorkflowStatesContract::STATUS_COMPLETED;
        } elseif ($this->isTimeout()) {
            $this->status = WorkflowStatesContract::STATUS_TIMEOUT;
        } elseif ($this->isStarted()) {
            $this->status = WorkflowStatesContract::STATUS_RUNNING;
        } else {
            $this->status = WorkflowStatesContract::STATUS_PENDING;
        }

        return $this;
    }
}
