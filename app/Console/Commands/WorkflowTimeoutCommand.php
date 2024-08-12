<?php

namespace App\Console\Commands;

use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\WorkflowRunRepository;
use Illuminate\Console\Command;

class WorkflowTimeoutCommand extends Command
{
    protected $signature   = 'workflow:timeout';
    protected $description = 'Checks for any workflow jobs that have timed out, and updates their statuses';

    public function handle()
    {
        $workflowTasks = WorkflowTask::where('status', WorkflowRun::STATUS_RUNNING)->withTrashed('workflowJobRun')->get();

        foreach($workflowTasks as $task) {
            if ($task->isTimedOut()) {
                app(WorkflowRunRepository::class)->taskTimedOut($task);
            }
        }
        $this->info("Workflow jobs have been checked for timeouts");
    }
}
