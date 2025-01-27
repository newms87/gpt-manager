<?php

namespace App\Console\Commands;

use App\Models\Task\TaskProcess;
use Illuminate\Console\Command;

class TaskTimeoutCommand extends Command
{
    protected $signature   = 'task:timeout';
    protected $description = 'Checks for any task processes that have timed out, and updates their statuses';

    public function handle()
    {
        $taskProcesses = TaskProcess::where('status', TaskProcess::STATUS_RUNNING)->get();

        foreach($taskProcesses as $taskProcess) {
            if ($taskProcess->isPastTimeout()) {
                $this->info("Task process timed out: $taskProcess");
                $taskProcess->timeout_at = now();
                $taskProcess->save();
            }
        }

        $this->info("All tasks have been checked for timeouts");
    }
}
