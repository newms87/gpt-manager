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
        $taskProcesses = TaskProcess::where('status', WorkflowStatesContract::STATUS_RUNNING)->get();

        foreach($taskProcesses as $taskProcess) {
            app(TaskProcessRepository::class)->checkForTimeout($taskProcess);
        }

        $this->info("All tasks have been checked for timeouts");
    }
}
