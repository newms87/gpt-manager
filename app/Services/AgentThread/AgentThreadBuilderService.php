<?php

namespace App\Services\AgentThread;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadRun;
use App\Models\Agent\McpServer;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Fluent builder for creating and configuring agent threads
 *
 * All threads have a default timeout of 60 seconds to prevent hanging.
 * Override with withTimeout() if needed.
 *
 * Example usage:
 *
 * Simple case:
 * $threadRun = AgentThreadBuilderService::for($agent)
 *     ->named('Content Search')
 *     ->withMessage($instructions)
 *     ->run();
 *
 * Complex case with artifacts:
 * $filter = new ArtifactFilter(includeText: true, includeJson: true, includeFiles: false);
 * $threadRun = AgentThreadBuilderService::for($agent, $teamId)
 *     ->named('Variable Resolution')
 *     ->withArtifacts($artifacts, $filter)
 *     ->withMessage($instructions)
 *     ->withResponseSchema($schema)
 *     ->withTimeout(120)
 *     ->run();
 *
 * Note: For TaskDefinition-specific usage, use TaskAgentThreadBuilderService instead.
 */
class AgentThreadBuilderService
{
    use HasDebugLogging;

    protected Agent $agent;

    protected ?int $teamId     = null;

    protected ?string $threadName = null;

    // Messages
    protected array $messages = [];

    // Artifact groups (each item: ['artifacts' => Collection, 'filter' => ?ArtifactFilter])
    protected array $artifactGroups     = [];

    protected bool $includePageNumbers = false;

    // Response configuration
    protected ?SchemaDefinition $responseSchema   = null;

    protected ?SchemaFragment $responseFragment = null;

    protected int $timeout          = 60; // Default 60 seconds

    protected ?McpServer $mcpServer        = null;

    // Built thread (cached)
    protected ?AgentThread $builtThread = null;

    /**
     * Static factory method for fluent API
     */
    public static function for(Agent $agent, ?int $teamId = null): static
    {
        $instance         = new static();
        $instance->agent  = $agent;
        $instance->teamId = $teamId ?? team()?->id;

        return $instance;
    }

    /**
     * Set thread name
     */
    public function named(string $name): static
    {
        $this->threadName = $name;

        return $this;
    }

    /**
     * Add a user message to the thread
     */
    public function withMessage(string|array|int|bool|null $content, array $fileIds = []): static
    {
        $this->messages[] = [
            'content' => $content,
            'fileIds' => $fileIds,
            'type'    => 'user',
        ];

        return $this;
    }

    /**
     * Add a system message to the thread
     */
    public function withSystemMessage(string|array $content): static
    {
        $this->messages[] = [
            'content' => $content,
            'fileIds' => [],
            'type'    => 'system',
        ];

        return $this;
    }

    /**
     * Add artifacts with optional filtering
     * Can be called multiple times to add different artifact groups with different filters
     */
    public function withArtifacts(
        array|Collection|EloquentCollection $artifacts,
        ?ArtifactFilter $filter = null
    ): static {
        $this->artifactGroups[] = [
            'artifacts' => collect($artifacts),
            'filter'    => $filter,
        ];

        return $this;
    }

    /**
     * Include page numbers in artifact messages
     */
    public function includePageNumbers(bool $include = true): static
    {
        $this->includePageNumbers = $include;

        return $this;
    }

    /**
     * Set response schema for JSON output
     */
    public function withResponseSchema(
        SchemaDefinition $schema,
        ?SchemaFragment $fragment = null
    ): static {
        $this->responseSchema   = $schema;
        $this->responseFragment = $fragment;

        return $this;
    }

    /**
     * Set MCP server
     */
    public function withMcpServer(McpServer $mcpServer): static
    {
        $this->mcpServer = $mcpServer;

        return $this;
    }

    /**
     * Set timeout in seconds (default is 60)
     */
    public function withTimeout(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Build the thread (without running it)
     */
    public function build(): AgentThread
    {
        // Return cached thread if already built
        if ($this->builtThread) {
            return $this->builtThread;
        }

        $this->validate();

        // Create the thread
        $thread = app(ThreadRepository::class)->create($this->agent, $this->threadName);

        // Add artifact groups
        $this->addArtifactGroupsToThread($thread);

        // Add messages
        $this->addMessagesToThread($thread);

        // Cache the built thread
        $this->builtThread = $thread;

        return $thread;
    }

    /**
     * Build and dispatch the thread asynchronously
     */
    public function dispatch(): AgentThreadRun
    {
        $thread  = $this->build();
        $service = $this->prepareService();

        return $service->dispatch($thread);
    }

    /**
     * Build and run the thread immediately
     */
    public function run(): AgentThreadRun
    {
        $thread  = $this->build();
        $service = $this->prepareService();

        return $service->run($thread);
    }

    public function prepareService(): AgentThreadService
    {
        $service = app(AgentThreadService::class);

        // Configure response format
        if ($this->responseSchema) {
            $service->withResponseFormat($this->responseSchema, $this->responseFragment);
        }

        // Configure MCP server
        if ($this->mcpServer) {
            $service->withMcpServer($this->mcpServer);
        }

        // Configure timeout (always set, defaults to 60 seconds)
        $service->withTimeout($this->timeout);

        return $service;
    }

    /**
     * Protected helper methods
     */
    protected function validate(): void
    {
        if (!isset($this->agent)) {
            throw new ValidationError('Agent is required to build thread');
        }

        // Validate file count if artifacts are included
        if (!empty($this->artifactGroups)) {
            $this->validateFileCount();
        }
    }

    protected function validateFileCount(int $maxFiles = 5): void
    {
        $totalFiles = 0;

        foreach ($this->artifactGroups as $group) {
            foreach ($group['artifacts'] as $artifact) {
                $filterService   = $this->resolveArtifactFilterService($artifact, $group['filter']);
                $filteredMessage = $filterService->setArtifact($artifact)->filter();

                if ($filteredMessage !== null && $filterService->hasFiles()) {
                    $totalFiles += $artifact->storedFiles()->count();
                }
            }
        }

        if ($totalFiles > $maxFiles) {
            throw new ValidationError("Too many files in artifacts: $totalFiles (max: $maxFiles)");
        }
    }

    protected function addArtifactGroupsToThread(AgentThread $thread): void
    {
        foreach ($this->artifactGroups as $group) {
            $artifacts = $group['artifacts'];
            $filter    = $group['filter'];

            if ($artifacts->isEmpty()) {
                continue;
            }

            foreach ($artifacts as $artifact) {
                $this->addSingleArtifact($thread, $artifact, $filter);
            }
        }
    }

    protected function addSingleArtifact(AgentThread $thread, Artifact $artifact, ?ArtifactFilter $filter): void
    {
        $filterService   = $this->resolveArtifactFilterService($artifact, $filter);
        $filteredMessage = $filterService->setArtifact($artifact)->filter();

        if ($filteredMessage !== null) {
            if ($this->includePageNumbers) {
                $filteredMessage = $this->injectPageNumber($artifact->position ?: 0, $filteredMessage);
            }

            app(ThreadRepository::class)->addMessageToThread($thread, $filteredMessage);
        }
    }

    protected function resolveArtifactFilterService(Artifact $artifact, ?ArtifactFilter $filter): ArtifactFilterService
    {
        $service = app(ArtifactFilterService::class);

        if ($filter) {
            // Apply the ArtifactFilter to the ArtifactFilterService
            $service->includeText($filter->includeText);
            $service->includeFiles($filter->includeFiles);
            $service->includeJson($filter->includeJson, $filter->jsonFragmentSelector);
            $service->includeMeta($filter->includeMeta, $filter->metaFragmentSelector);
        }

        return $service;
    }

    protected function injectPageNumber(int $pageNumber, string|array $message): string|array
    {
        $pageStr = "# Page $pageNumber\n\n";

        if (is_string($message)) {
            return "$pageStr$message";
        }

        if (!empty($message['text_content'])) {
            $message['text_content'] = "$pageStr{$message['text_content']}";
        }

        return $message;
    }

    protected function addMessagesToThread(AgentThread $thread): void
    {
        foreach ($this->messages as $message) {
            app(ThreadRepository::class)->addMessageToThread(
                $thread,
                $message['content'],
                $message['fileIds'] ?? []
            );
        }
    }
}
