<?php

namespace App\Services\Task\Runners;

use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\Task\TaskRunnerService;
use Exception;
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

        if (!$agent) {
            throw new Exception("AgentThreadTaskRunner: Agent not found for TaskProcess: $this->taskProcess");
        }

        $inputArtifacts = $this->taskProcess->inputArtifacts()->get();

        $threadName = $definition->name . ': ' . $agent->name;
        $thread     = app(ThreadRepository::class)->create($agent, $threadName);

        Log::debug("Setup Task Thread: $thread");

        Log::debug("\tAdding " . count($inputArtifacts) . " input artifacts");
        $artifactFilter = (new ArtifactFilter())
            ->includeText($definitionAgent->include_text)
            ->includeFiles($definitionAgent->include_files)
            ->includeData($definitionAgent->include_data, $definitionAgent->input_sub_selection);

        foreach($inputArtifacts as $inputArtifact) {
            $artifactFilter->setArtifact($inputArtifact);
            app(ThreadRepository::class)->addMessageToThread($thread, $artifactFilter->filter());
        }

        $this->taskProcess->thread()->associate($thread)->save();

        return $thread;
    }
}
