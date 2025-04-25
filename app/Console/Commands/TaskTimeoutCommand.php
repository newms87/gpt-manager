<?php

namespace App\Console\Commands;

use App\Repositories\TaskProcessRepository;
use Illuminate\Console\Command;

class TaskTimeoutCommand extends Command
{
    protected $signature   = 'task:timeout';
    protected $description = 'Checks for any task processes that have timed out, and updates their statuses';

    public function handle()
    {
        app(TaskProcessRepository::class)->checkForTimeouts();

        $this->info("All tasks have been checked for timeouts");
    }
}
