<?php

namespace App\Services\Task\Runners;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Services\Task\TaskProcessRunnerService;
use Illuminate\Database\Eloquent\Collection;

/**
 * The runner processes all artifacts and applies category classification based on the user's prompt.
 * Behavior summary:
 *
 * - Each input artifact will be assigned a `__category` field (in JSON) with the category determined by the process.
 * - After all task processes finish:
 *   - Artifacts with the `__exclude` category will be removed from the final output.
 *   - Artifacts will be sorted based on their `position` field.
 *   - Any artifact categorized as `__unknown` will attempt to be matched with other artifact categories that were
 *   identified before and after artifact in the sequence sorted list.
 * - The final list of artifacts (after filtering and sorting) will be set as the output.
 */
class CategorizeArtifactsTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Categorize Artifacts';

    // Special category constants
    const string
        CATEGORY_UNKNOWN = '__unknown',
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

        $this->applyCategories($categoryArtifact->json_content['categories'] ?? [], $inputArtifacts);

        $this->activity('Categorization complete', 95);

        // Complete the task with the input artifacts
        // Note: The final processing of artifacts (sorting, category matching, etc.)
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
    protected function applyCategories(array $categoryList, Collection $artifacts): void
    {
        foreach($categoryList as $categoryItem) {
            $category = $categoryItem['category'] ?? self::CATEGORY_UNKNOWN;

            // If any part of the category name has the unknown flag, the whole category is unknown
            if (str_contains($category, self::CATEGORY_UNKNOWN)) {
                $category = self::CATEGORY_UNKNOWN;
            }

            $pages = $categoryItem['pages'] ?? [];

            foreach($pages as $page) {
                // Find the artifact with the given page number
                /** @var Artifact|null $artifact */
                $artifact = $artifacts->firstWhere('position', $page);

                if ($artifact) {
                    // Add the category to the artifact's JSON content
                    $jsonContent               = $artifact->json_content ?? [];
                    $jsonContent['__category'] = $category;
                    $artifact->json_content    = $jsonContent;
                    $artifact->save();
                }
            }
        }
    }

    /**
     * Create a schema definition for the category response
     */
    protected function createCategorySchema(): SchemaDefinition
    {
        // Build the category description based on available categories
        $categoryDescription = "Classify the artifact by choosing the most appropriate category. " .
            "In addition to user-defined categories, you may also use the following options:\n\n" .
            "* " . self::CATEGORY_UNKNOWN . ": Use this if you're not 100% confident about the category.\n" .
            "* " . self::CATEGORY_EXCLUDE . ": Use this to exclude the artifact entirely from the output if the user has requested artifacts to be excluded under certain conditions.";

        $pagesDescription = "List the page numbers given in the artifact that belong to the category. If you are 100% confident that the artifact belongs to the category, add the page number of the artifact to this list.";

        return SchemaDefinition::make([
            'name'   => 'Category',
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'categories' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'category' => [
                                    'type'        => 'string',
                                    'description' => $categoryDescription,
                                ],
                                'pages'    => [
                                    'type'        => 'array',
                                    'description' => $pagesDescription,
                                    'items'       => ['type' => 'number'],
                                ],
                            ],
                            'required'   => ['category', 'pages'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Process the final list of artifacts after all task processes are complete
     * 1. Sort artifacts by position
     * 2. Remove artifacts with __exclude category
     * 3. Perform category matching
     */
    public function afterAllProcessesCompleted(): void
    {
        $this->activity('Processing final artifacts', 95);

        // Get all output artifacts from all processes
        $finalOutputArtifacts = $this->taskRun->outputArtifacts;

        // 1. Sort all the artifacts
        static::log("Sorting artifacts by position");
        $finalOutputArtifacts = $finalOutputArtifacts->sort(fn(Artifact $a, Artifact $b) => $a->position <=> $b->position);

        // 2. Exclude artifacts with __exclude category
        static::log("Excluding artifacts with __exclude category");
        $finalOutputArtifacts = $finalOutputArtifacts->filter(fn(Artifact $outputArtifact) => $outputArtifact->json_content['__category'] ?? null !== self::CATEGORY_EXCLUDE);

        // 2. Attempt category matching if the task is in sequential mode
        if ($this->isSequentialMode()) {
            $this->matchSequentialCategories($finalOutputArtifacts);
        }

        // Update the task run's output artifacts
        $this->taskRun->outputArtifacts()->sync($finalOutputArtifacts->pluck('id'));
        $this->taskRun->updateRelationCounter('outputArtifacts');

        $this->activity('Final artifact processing complete', 100);
    }

    /**
     * Collect sequential artifacts with the __unknown category and the previous and next resolved category into a group
     * And attempt to match the unknown categorized artifacts to a category based on its sequence in the group
     * @param Artifact[] $artifacts
     */
    protected function matchSequentialCategories($artifacts): void
    {
        static::log("Applying sequential categories to artifacts");

        /** @var Artifact[][] $categoryGroups */
        $categoryGroups = [];

        $groupKey = '';

        foreach($artifacts as $artifact) {
            $category = $artifact->json_content['__category'] ?? null;

            // If category is unknown, add it to the current group to attempt to resolve with the previous and next known category
            if ($category === self::CATEGORY_UNKNOWN) {
                // Add the category
                $categoryGroups[$groupKey][] = $artifact;
            } else {
                // Add to the current group to see if previous artifacts belong to the same group
                $categoryGroups[$groupKey][] = $artifact;

                // Changing the group key to start a new group (or add to an existing group if it already had existed)
                $groupKey = $category;

                // Add to the next group to see if subsequent artifacts belong to the same group
                $categoryGroups[$groupKey][] = $artifact;
            }
        }

        $groupsWithUnknowns = [];
        foreach($categoryGroups as $categoryGroupArtifacts) {
            foreach($categoryGroupArtifacts as $categoryGroupArtifact) {
                if ($categoryGroupArtifact->json_content['__category'] ?? null === self::CATEGORY_UNKNOWN) {
                    $groupsWithUnknowns[] = $categoryGroupArtifacts;
                    break;
                }
            }
        }

        $this->dispatchCategoryGroups($groupsWithUnknowns);
    }

    private function dispatchCategoryGroups(array $categoryGroups): void
    {
        static::log("Dispatching " . count($categoryGroups) . " category groups for sequential matching");

        $taskProcesses = [];
        /** @var Artifact[] $categoryGroupArtifacts */
        foreach($categoryGroups as $categoryGroupArtifacts) {
            $taskProcesses[] = TaskProcessRunnerService::prepare($this->taskRun, null, $categoryGroupArtifacts);
        }

        foreach($taskProcesses as $taskProcess) {
            TaskProcessRunnerService::dispatch($taskProcess);
        }
    }
}
