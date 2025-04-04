<?php

namespace App\Services\Task\Runners;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * The runner processes all artifacts and applies category classification based on the user's prompt.
 * Behavior summary:
 *
 * - Each input artifact will be assigned a `__category` field (in JSON) with the category determined by the process.
 * - After all task processes finish:
 *   - Artifacts with the `__exclude` category will be removed from the final output.
 *   - Artifacts will be sorted based on their `position` field.
 *   - Any artifact categorized as `__previous` will inherit the category of the artifact immediately before it in the
 *   sorted list.
 * - The final list of artifacts (after filtering and sorting) will be set as the output.
 */
class CategorizeArtifactsTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Categorize Artifacts';

    // Special category constants
    const string
        CATEGORY_USE_PREVIOUS = '__previous',
        CATEGORY_EXCLUDE = '__exclude';

    // Flag to enable sequential mode
    protected bool $sequentialMode = false;

    /**
     * Initial activity message and
     */
    public function prepareProcess(): void
    {
        parent::prepareProcess();

        $this->activity('Preparing to categorize artifacts' . ($this->sequentialMode ? ' in sequential mode' : ''), 5);
    }

    public function run(): void
    {
        $taskDefinition = $this->taskRun->taskDefinition;
        $agent          = $taskDefinition->agent;

        // Make sure to include page numbers in the agent thread so the agent can reference them
        $this->includePageNumbersInThread = true;

        // Get all input artifacts
        $inputArtifacts = $this->taskProcess->inputArtifacts;
        $totalArtifacts = $inputArtifacts->count();

        if ($totalArtifacts === 0) {
            $this->activity('No artifacts to categorize', 100);
            $this->complete([]);

            return;
        }

        // Setup the agent thread
        $agentThread = $this->setupAgentThread();

        $this->activity("Using agent to categorize $totalArtifacts artifacts: " . $agent->name, 10);

        $schema           = $this->createCategorySchema();
        $categoryArtifact = $this->runAgentThreadWithSchema($agentThread, $schema);

        $category = $categoryArtifact->json_content['category'] ?? null;

        if (!$category) {
            throw new ValidationError('No category provided by the agent');
        }

        foreach($inputArtifacts as $artifact) {
            $this->applyCategory($artifact, $category);
        }

        $this->activity('Categorization complete', 95);

        // Complete the task with the input artifacts
        // Note: The final processing of artifacts (sorting, applying __previous, etc.)
        // will be done when all task processes are complete
        $this->complete($inputArtifacts);
    }

    /**
     * Check if the task is running in sequential mode
     */
    protected function isSequentialMode(): bool
    {
        return $this->taskRun->taskDefinition->task_runner_config['sequential_mode'] ?? true;
    }

    /**
     * Apply the category to the artifact's JSON content
     */
    protected function applyCategory(Artifact $artifact, string $category): void
    {
        // Add the category to the artifact's JSON content
        $jsonContent               = $artifact->json_content ?? [];
        $jsonContent['__category'] = $category;
        $artifact->json_content    = $jsonContent;
        $artifact->save();
    }

    /**
     * Create a schema definition for the category response
     */
    protected function createCategorySchema(): SchemaDefinition
    {
        // Build the category description based on available categories
        $categoryDescription = "Classify the artifact by choosing the most appropriate category. " .
            "In addition to user-defined categories, you may also use the following options:\n\n";

        if ($this->isSequentialMode()) {
            $categoryDescription .= "* " . self::CATEGORY_USE_PREVIOUS . ": Use this if you're not 100% confident about the category, and the artifact seems to continue or relate to the previous one (e.g., part of a series or sequential pages).\n";
        }

        $categoryDescription .= "* " . self::CATEGORY_EXCLUDE . ": Use this to exclude the artifact entirely from the output.";

        return SchemaDefinition::make([
            'name'   => 'Category',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'category' => [
                        'type'        => 'string',
                        'description' => $categoryDescription,
                    ],
                ],
                'required'   => ['category'],
            ],
        ]);
    }

    /**
     * Process the final list of artifacts after all task processes are complete
     * - Remove artifacts with __exclude category
     * - Sort artifacts by position
     * - Apply __previous category
     */
    public function afterAllProcessesCompleted(): void
    {
        $this->activity('Processing final artifacts', 95);

        // Get all output artifacts from all processes
        $finalOutputArtifacts = $this->taskRun->outputArtifacts;

        // 1. Sort all the artifacts
        static::log("Sorting artifacts by position");
        $finalOutputArtifacts = $finalOutputArtifacts->sort(fn(Artifact $a, Artifact $b) => $a->position <=> $b->position);

        // 2. Apply __previous category if the task is in sequential mode
        if ($this->isSequentialMode()) {
            $this->applySequentialCategories($finalOutputArtifacts);
        }

        // 3. Exclude artifacts with __exclude category
        static::log("Excluding artifacts with __exclude category");
        $finalOutputArtifacts = $finalOutputArtifacts->filter(fn(Artifact $outputArtifact) => $outputArtifact->json_content['__category'] ?? null !== self::CATEGORY_EXCLUDE);

        // Update the task run's output artifacts
        $this->taskRun->outputArtifacts()->sync($finalOutputArtifacts->pluck('id'));
        $this->taskRun->updateRelationCounter('outputArtifacts');

        $this->activity('Final artifact processing complete', 100);
    }

    /**
     * Replace any artifacts with the __previous category with the category of the preceding artifact in sequential mode
     */
    protected function applySequentialCategories($artifacts): void
    {
        static::log("Applying sequential categories to artifacts");

        $lastCategory = null;

        foreach($artifacts as $artifact) {
            $category = $artifact->json_content['__category'] ?? null;

            // If category is __previous
            if ($category === self::CATEGORY_USE_PREVIOUS) {
                // If there is no last category, something went wrong. The first artifact should always have a category set
                if (!$lastCategory) {
                    throw new ValidationError("No previous category found for artifact $artifact");
                }

                $jsonContent               = $artifact->json_content;
                $jsonContent['__category'] = $lastCategory;
                $artifact->json_content    = $jsonContent;
                $artifact->save();

                static::log("Applying category $lastCategory to $artifact");
            } else {
                // Store the last valid category
                $lastCategory = $category;
            }
        }
    }
}
