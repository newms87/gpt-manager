<?php

namespace App\Console\Commands;

use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowStatesContract;
use App\Repositories\TaskProcessRepository;
use Illuminate\Console\Command;

class TaskTimeoutCommand extends Command
{
    protected $signature   = 'task:timeout';
    protected $description = 'Checks for any task processes that have timed out, and updates their statuses';

    public function handle()
    {
        $statuses         = [WorkflowStatesContract::STATUS_RUNNING, WorkflowStatesContract::STATUS_PENDING, WorkflowStatesContract::STATUS_DISPATCHED];
        $runningProcesses = TaskProcess::whereIn('status', $statuses)->get();

        foreach($runningProcesses as $taskProcess) {
            app(TaskProcessRepository::class)->checkForTimeout($taskProcess);
        }

        $this->info("All tasks have been checked for timeouts");
    }
}
