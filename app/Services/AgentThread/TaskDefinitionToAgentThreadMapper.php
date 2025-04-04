<?php

namespace App\Services\AgentThread;

use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Repositories\ThreadRepository;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TaskDefinitionToAgentThreadMapper
{
    use HasDebugLogging;

    protected TaskDefinition $taskDefinition;
    /** @var array|Collection|EloquentCollection|Artifact[] */
    protected array|Collection|EloquentCollection $artifacts             = [];
    protected ?ArtifactFilterService              $artifactFilterService = null;

    protected array $messages = [];

    public function setArtifactFilterService(ArtifactFilterService $artifactFilterService): static
    {
        $this->artifactFilterService = $artifactFilterService;

        return $this;
    }

    public function setTaskDefinition(TaskDefinition $taskDefinition): static
    {
        $this->taskDefinition = $taskDefinition;

        return $this;
    }

    public function setArtifacts(array|Collection|EloquentCollection $artifacts): static
    {
        $this->artifacts = $artifacts;

        return $this;
    }

    public function addMessage(string|array|int|bool $message): static
    {
        $this->messages[] = $message;

        return $this;
    }

    public function map(): AgentThread
    {
        static::log("Mapping to agent thread: $this->taskDefinition");

        $agent = $this->taskDefinition->agent;

        if (!$agent) {
            throw new Exception("Agent not found for Task Definition: $this->taskDefinition");
        }

        $threadName  = $this->taskDefinition->name . ': ' . $agent->name;
        $agentThread = app(ThreadRepository::class)->create($agent, $threadName);

        $this->addDirectives($agentThread, $this->taskDefinition->beforeThreadDirectives()->get());
        $this->addArtifacts($agentThread);
        $this->appendMessages($agentThread);
        $this->addDirectives($agentThread, $this->taskDefinition->afterThreadDirectives()->get());

        return $agentThread;
    }

    /**
     * @param AgentThread                                  $agentThread
     * @param EloquentCollection|TaskDefinitionDirective[] $taskDefinitionDirectives
     * @return void
     */
    protected function addDirectives(AgentThread $agentThread, EloquentCollection|array $taskDefinitionDirectives): void
    {
        foreach($taskDefinitionDirectives as $taskDefinitionDirective) {
            if ($taskDefinitionDirective->directive->directive_text) {
                app(ThreadRepository::class)->addMessageToThread($agentThread, $taskDefinitionDirective->directive->directive_text);
            }
        }
    }

    /**
     * Formats and adds all the artifacts to the thread
     */
    protected function addArtifacts(AgentThread $agentThread): void
    {
        static::log("\tAdding " . count($this->artifacts) . " input artifacts");

        foreach($this->artifacts as $artifact) {
            $filteredMessage = $this->artifactFilterService->setArtifact($artifact)->filter();
            if ($filteredMessage) {
                app(ThreadRepository::class)->addMessageToThread($agentThread, $filteredMessage);
                static::log("\tAdded artifact " . $artifact->id);
            } else {
                static::log("\tSkipped artifact (was empty) " . $artifact->id);
            }
        }
    }

    /**
     * Adds the messages to the thread
     */
    protected function appendMessages(AgentThread $agentThread): void
    {
        static::log("\tAdding " . count($this->messages) . " messages to thread");
        foreach($this->messages as $message) {
            app(ThreadRepository::class)->addMessageToThread($agentThread, $message);
        }
    }
}
