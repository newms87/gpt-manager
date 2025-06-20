<?php

namespace App\Models\Workflow;

interface WorkflowStatesContract
{
    const string
        STATUS_PENDING = 'Pending',
        STATUS_RUNNING = 'Running',
        STATUS_STOPPED = 'Stopped',
        STATUS_SKIPPED = 'Skipped',
        STATUS_COMPLETED = 'Completed',
        STATUS_TIMEOUT = 'Timeout',
        STATUS_FAILED = 'Failed';

    public function isStatusPending(): bool;

    public function isStatusRunning(): bool;

    public function isStarted(): bool;

    public function isStopped(): bool;

    public function isFailed(): bool;

    public function isCompleted(): bool;

    public function isTimedout(): bool;

    public function isFinished(): bool;

    public function canContinue(): bool;

    public function computeStatus(): static;
}
