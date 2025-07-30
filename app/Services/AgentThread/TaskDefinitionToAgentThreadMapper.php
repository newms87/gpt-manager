<?php

namespace App\Services\AgentThread;

use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TaskDefinitionToAgentThreadMapper
{
    use HasDebugLogging;

    protected int $maxFiles = 5;

    protected ?TaskRun       $taskRun = null;
    protected TaskDefinition $taskDefinition;
    /** @var Collection|EloquentCollection|Artifact[] */
    protected Collection|EloquentCollection $artifacts;
    /** @var Collection|EloquentCollection|Artifact[] */
    protected Collection|EloquentCollection $contextArtifacts;

    protected bool  $includePageNumbers = false;
    protected array $messages           = [];

    public function __construct()
    {
        $this->artifacts        = collect();
        $this->contextArtifacts = collect();
    }

    public function includePageNumbers(bool $included = true): static
    {
        $this->includePageNumbers = $included;

        return $this;
    }

    public function setTaskRun(TaskRun $taskRun): static
    {
        $this->taskRun = $taskRun;

        return $this;
    }

    public function setTaskDefinition(TaskDefinition $taskDefinition): static
    {
        $this->taskDefinition = $taskDefinition;

        return $this;
    }

    public function setArtifacts(array|Collection|EloquentCollection $artifacts): static
    {
        $this->artifacts = collect($artifacts);

        return $this;
    }

    public function setContextArtifacts(array|Collection|EloquentCollection $contextArtifacts = []): static
    {
        $this->contextArtifacts = collect($contextArtifacts);

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

        $this->validate();

        $agent = $this->taskDefinition->agent;

        $threadName  = $this->taskDefinition->name . ': ' . $agent->name;
        $agentThread = app(ThreadRepository::class)->create($agent, $threadName);

        $this->addDirectives($agentThread, $this->taskDefinition->beforeThreadDirectives()->with('directive')->get());
        $this->addArtifacts($agentThread);
        $this->appendMessages($agentThread);
        $this->addDirectives($agentThread, $this->taskDefinition->afterThreadDirectives()->with('directive')->get());

        return $agentThread;
    }

    private function validate(): void
    {
        if (!$this->taskDefinition->agent) {
            throw new Exception("Agent not found for Task Definition: $this->taskDefinition");
        }

        $totalFiles = 0;
        foreach($this->artifacts as $artifact) {
            $artifactFilterService = $this->resolveArtifactFilterService($artifact);
            $filteredMessage       = $artifactFilterService->setArtifact($artifact)->filter();

            // Only count files if the filtered artifact will actually be sent (not null)
            // and the filter includes files
            if ($filteredMessage !== null && $artifactFilterService->hasFiles()) {
                $totalFiles += $artifact->storedFiles()->count();
            }
        }

        if ($totalFiles > $this->maxFiles) {
            throw new Exception("Too many files in artifacts: $totalFiles (max: $this->maxFiles)");
        }
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
        static::log("\tAdding " . $this->artifacts->count() . " primary artifacts");

        $minPosition            = $this->artifacts->min('position');
        $maxPosition            = $this->artifacts->max('position');
        $contextBeforeArtifacts = $this->contextArtifacts->filter(fn(Artifact $a) => $a->position < $minPosition)->sortBy('position');
        $contextAfterArtifacts  = $this->contextArtifacts->filter(fn(Artifact $a) => $a->position > $maxPosition)->sortBy('position');

        // Add context before if available
        if ($contextBeforeArtifacts->isNotEmpty()) {
            app(ThreadRepository::class)->addMessageToThread($agentThread, "\n--- CONTEXT BEFORE ---\n");
            foreach($contextBeforeArtifacts as $artifact) {
                $this->addSingleArtifact($agentThread, $artifact, true);
            }
        }

        // Add primary artifacts
        if ($this->artifacts->isNotEmpty()) {
            app(ThreadRepository::class)->addMessageToThread($agentThread, "\n--- PRIMARY ARTIFACTS ---\n");
            foreach($this->artifacts as $artifact) {
                $this->addSingleArtifact($agentThread, $artifact, false);
            }
        }

        // Add context after if available
        if ($contextAfterArtifacts->isNotEmpty()) {
            app(ThreadRepository::class)->addMessageToThread($agentThread, "\n--- CONTEXT AFTER ---\n");
            foreach($contextAfterArtifacts as $artifact) {
                $this->addSingleArtifact($agentThread, $artifact, true);
            }
        }
    }

    /**
     * Add a single artifact to the thread
     */
    protected function addSingleArtifact(AgentThread $agentThread, Artifact $artifact, bool $isContext = false): void
    {
        $artifactFilterService = $this->resolveArtifactFilterService($artifact);
        $filteredMessage       = $artifactFilterService->setArtifact($artifact)->filter();

        if ($filteredMessage) {
            if ($this->includePageNumbers) {
                $filteredMessage = $this->injectPageNumber($artifact->position ?: 0, $filteredMessage);
            }

            app(ThreadRepository::class)->addMessageToThread($agentThread, $filteredMessage);
            static::log("\tAdded " . ($isContext ? "context" : "primary") . " artifact " . $artifact->id);
        } else {
            static::log("\tSkipped " . ($isContext ? "context" : "primary") . " artifact (was empty) " . $artifact->id);
        }
    }

    /**
     * Resolves the artifact filter service for the given source task definition based on the artifact filters defined
     * for the target task definition
     */
    protected function resolveArtifactFilterService(Artifact $artifact): ArtifactFilterService
    {
        $service = app(ArtifactFilterService::class);

        if (!$this->taskRun) {
            return $service;
        }

        foreach($this->taskDefinition->taskArtifactFiltersAsTarget as $artifactFilter) {
            if ($artifactFilter->source_task_definition_id === $artifact->task_definition_id) {
                return $service->setFilter($artifactFilter);
            }
        }

        return $service;
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

    /**
     * Injects the page number into the message
     */
    protected function injectPageNumber(string $pageNumber, $message): string|array
    {
        $pageStr = "# Page $pageNumber\n\n";

        if (is_string($message)) {
            return "$pageStr $message";
        }

        if (!empty($message['text_content'])) {
            $message['text_content'] = "$pageStr {$message['text_content']}";
        }

        return $message;
    }
}
