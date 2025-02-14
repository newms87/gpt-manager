<?php

namespace App\Models\Task;

interface WorkflowStatesContract
{
    const string
        STATUS_PENDING = 'Pending',
        STATUS_DISPATCHED = 'Dispatched',
        STATUS_RUNNING = 'Running',
        STATUS_STOPPED = 'Stopped',
        STATUS_COMPLETED = 'Completed',
        STATUS_TIMEOUT = 'Timeout',
        STATUS_FAILED = 'Failed';

    const array STATUSES = [
        WorkflowStatesContract::STATUS_PENDING,
        WorkflowStatesContract::STATUS_DISPATCHED,
        WorkflowStatesContract::STATUS_RUNNING,
        WorkflowStatesContract::STATUS_COMPLETED,
        WorkflowStatesContract::STATUS_TIMEOUT,
        WorkflowStatesContract::STATUS_FAILED,
    ];

    public function isPending(): bool;

    public function isRunning(): bool;

    public function isStarted(): bool;

    public function isStopped(): bool;

    public function isFailed(): bool;

    public function isCompleted(): bool;

    public function isTimeout(): bool;

    public function isFinished(): bool;

    public function canContinue(): bool;

    public function computeStatus(): static;
}
