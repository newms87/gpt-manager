<?php

namespace App\Services\Task\Runners;

use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Task\TaskRunnerService;
use Illuminate\Support\Facades\Log;

class AgentThreadTaskRunner extends TaskRunnerAbstract
{
    public function run(): void
    {
        $thread = $this->setupAgentThread();

        // Run the thread synchronously (ie: dispatch = false)
        (new AgentThreadService)->run($thread, dispatch: false);

        // Finished running the process
        TaskRunnerService::processCompleted($this->taskProcess);
    }

    public function setupAgentThread()
    {
        $definitionAgent = $this->taskProcess->taskDefinitionAgent;
        $definition      = $definitionAgent->taskDefinition;
        $agent           = $definitionAgent->agent;

        $threadName = $definition->name . ': ' . $agent->name;
        $thread     = app(ThreadRepository::class)->create($agent, $threadName);

        Log::debug("Setup Task Thread: $thread");

        //        Log::debug("\tAdding " . count($artifactTuple) . " artifacts");
        //        foreach($artifactTuple as $item) {
        //            app(ThreadRepository::class)->addMessageToThread($thread, $item);
        //        }

        $this->taskProcess->thread()->associate($thread)->save();

        return $thread;
    }
}
