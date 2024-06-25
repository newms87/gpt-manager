<?php

namespace App\Jobs;

use App\Models\Agent\ThreadRun;
use App\Services\AgentThread\AgentThreadService;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Jobs\Job;

class ExecuteThreadRunJob extends Job
{
    public ThreadRun $threadRun;

    public function __construct(ThreadRun $threadRun)
    {
        $this->threadRun = $threadRun;
        Log::debug("ExecuteThreadRunJob created for thread run $threadRun->id");
        parent::__construct();
    }

    public function ref(): string
    {
        return 'execute-thread-run:' . $this->threadRun->id;
    }

    public function run()
    {
        app(AgentThreadService::class)->executeThreadRun($this->threadRun);
    }
}
