<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\ArtifactFilter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Task-specific extension of AgentThreadBuilderService
 *
 * Handles TaskDefinition and TaskRun specific concerns:
 * - Loading directives and prompts from TaskDefinition
 * - Resolving TaskArtifactFilters to ArtifactFilters
 * - Position-based context artifact grouping
 *
 * Example usage:
 *
 * Simple case from TaskDefinition:
 * $threadRun = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition)
 *     ->withArtifacts($artifacts)
 *     ->includePageNumbers()
 *     ->run();
 *
 * With context artifacts:
 * $builder = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun)
 *     ->withContextArtifacts($artifacts, $contextArtifacts)
 *     ->withResponseSchema($schema)
 *     ->run();
 */
class TaskAgentThreadBuilderService extends AgentThreadBuilderService
{
    protected ?TaskRun        $taskRun            = null;
    protected ?TaskDefinition $taskDefinition     = null;
    protected ?Collection     $primaryArtifacts   = null;

    /**
     * Create builder from TaskDefinition with directives and prompt
     */
    public static function fromTaskDefinition(TaskDefinition $taskDefinition, ?TaskRun $taskRun = null): static
    {
        $instance         = new static();
        $instance->agent  = $taskDefinition->agent;
        $instance->teamId = $taskRun?->team_id ?? team()?->id;

        $instance->taskDefinition = $taskDefinition;
        $instance->taskRun        = $taskRun;

        // Set default name
        $instance->named($taskDefinition->name . ': ' . $taskDefinition->agent->name);

        // Load directives and prompt from TaskDefinition
        $instance->loadDirectivesAndPrompt();

        return $instance;
    }

    /**
     * Add artifacts with optional filtering
     * Resolves TaskArtifactFilters to ArtifactFilters if TaskRun context is set
     * Overrides parent to add task-specific filter resolution
     */
    public function withArtifacts(
        array|Collection|EloquentCollection $artifacts,
        ?ArtifactFilter                     $filter = null
    ): static
    {
        // Store for context artifact positioning
        $this->primaryArtifacts = collect($artifacts);

        // If we have task context, check for specific artifact filters
        if ($this->taskRun && $this->taskDefinition && $this->primaryArtifacts->isNotEmpty()) {
            foreach($this->primaryArtifacts as $artifact) {
                $resolvedFilter = $this->resolveTaskArtifactFilter($artifact) ?? $filter;
                parent::withArtifacts([$artifact], $resolvedFilter);
            }
        } else {
            // No task context or empty collection, use parent implementation directly
            parent::withArtifacts($this->primaryArtifacts, $filter);
        }

        return $this;
    }

    /**
     * Add context artifacts with position-based grouping
     * Splits context into "before" and "after" groups based on artifact positions
     */
    public function withContextArtifacts(
        array|Collection|EloquentCollection $artifacts,
        array|Collection|EloquentCollection $contextArtifacts
    ): static
    {
        $artifacts        = collect($artifacts);
        $contextArtifacts = collect($contextArtifacts);

        if ($contextArtifacts->isEmpty() || $artifacts->isEmpty()) {
            // No context to add, just add primary artifacts
            if ($artifacts->isNotEmpty()) {
                $this->withMessage("\n--- PRIMARY ARTIFACTS ---\n");
                $this->withArtifacts($artifacts);
            }

            return $this;
        }

        // Calculate position boundaries
        $minPosition = $artifacts->min('position');
        $maxPosition = $artifacts->max('position');

        // Split context into before/after based on position
        $contextBefore = $contextArtifacts
            ->filter(fn(Artifact $a) => $a->position < $minPosition)
            ->sortBy('position');

        $contextAfter = $contextArtifacts
            ->filter(fn(Artifact $a) => $a->position > $maxPosition)
            ->sortBy('position');

        // Add context before if available
        if ($contextBefore->isNotEmpty()) {
            $this->withMessage("\n--- CONTEXT BEFORE ---\n");
            $this->withArtifacts($contextBefore);
        }

        // Add primary artifacts with header
        $this->withMessage("\n--- PRIMARY ARTIFACTS ---\n");
        $this->withArtifacts($artifacts);

        // Add context after if available
        if ($contextAfter->isNotEmpty()) {
            $this->withMessage("\n--- CONTEXT AFTER ---\n");
            $this->withArtifacts($contextAfter);
        }

        return $this;
    }

    /**
     * Load directives and prompt from TaskDefinition
     */
    protected function loadDirectivesAndPrompt(): void
    {
        if (!$this->taskDefinition) {
            return;
        }

        // Add before-thread directives as messages
        foreach($this->taskDefinition->beforeThreadDirectives()->with('directive')->get() as $directive) {
            if ($directive->directive->directive_text) {
                $this->withMessage($directive->directive->directive_text);
            }
        }

        // Add prompt if exists
        if ($this->taskDefinition->prompt) {
            $this->withMessage($this->taskDefinition->prompt);
        }

        // Add after-thread directives as messages
        foreach($this->taskDefinition->afterThreadDirectives()->with('directive')->get() as $directive) {
            if ($directive->directive->directive_text) {
                $this->withMessage($directive->directive->directive_text);
            }
        }
    }

    /**
     * Resolve TaskArtifactFilter to ArtifactFilter for a specific artifact
     * Returns null if no specific filter found
     */
    protected function resolveTaskArtifactFilter(Artifact $artifact): ?ArtifactFilter
    {
        if (!$this->taskRun || !$this->taskDefinition) {
            return null;
        }

        // Check for specific artifact filters matching this artifact's source
        foreach($this->taskDefinition->taskArtifactFiltersAsTarget as $taskFilter) {
            if ($taskFilter->source_task_definition_id === $artifact->task_definition_id) {
                // Convert TaskArtifactFilter to ArtifactFilter
                return $taskFilter->toArtifactFilter();
            }
        }

        return null;
    }
}
